<?php
session_start();
include 'db_connect.php';

// Security: Only Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$admin_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = "";

// 1. Fetch Student Data
$student_q = $conn->query("SELECT * FROM users WHERE id=$student_id AND role='student_teacher'");
if ($student_q->num_rows == 0) {
    echo "<script>alert('Student not found.'); window.location.href='admin_dashboard.php';</script>";
    exit();
}
$student = $student_q->fetch_assoc();

// 2. Handle New General Evaluation Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eval_title = $_POST['eval_title'];
    $score = $_POST['score'];
    $notes = $_POST['notes'];
    
    $target_dir = "uploads/";
    $target_file = "";
    if (!empty($_FILES['file']['name'])) {
        $file_name = basename($_FILES["file"]["name"]);
        $target_file = $target_dir . $student_id . "_admin_eval_" . time() . "_" . $file_name;
        move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
    }

    // Insert Evaluation
    $stmt = $conn->prepare("INSERT INTO evaluations (user_id, evaluator_id, evaluation_title, competency_score, readiness_notes, file_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iisiss", $student_id, $admin_id, $eval_title, $score, $notes, $target_file);
    
    if($stmt->execute()) {
        $msg = "General evaluation submitted successfully!";
    } else {
        $msg = "Database Error.";
    }
}

// 3. Fetch History & Graph Data
$history_q = $conn->query("SELECT e.*, ev.fullname as evaluator_name, ev.role as evaluator_role 
                           FROM evaluations e 
                           LEFT JOIN users ev ON e.evaluator_id = ev.id
                           WHERE e.user_id = $student_id 
                           ORDER BY e.upload_date ASC");

$dates = [];
$scores = [];
$history_data = [];

while($row = $history_q->fetch_assoc()) {
    if($row['status'] == 'accepted') {
        $dates[] = date("M d", strtotime($row['upload_date']));
        $scores[] = $row['competency_score'];
    }
    $history_data[] = $row; 
}
$history_data = array_reverse($history_data);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin View: <?php echo htmlspecialchars($student['fullname']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function insertQuickText() {
            var select = document.getElementById("quickText");
            var textarea = document.getElementById("notesArea");
            if(select.value !== "") {
                textarea.value += (textarea.value ? "\n" : "") + select.value;
                select.selectedIndex = 0;
            }
        }
    </script>
    <style>
        .split-view { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 1000px) { .split-view { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>


    <div class="main-content">
        
        <div class="top-header">
            <div class="greeting-box">
                <h2><span id="greetingText">Welcome,</span> <?php echo htmlspecialchars($_SESSION['fullname']); ?></h2>
                <div id="currentDate" class="date-box">Loading date...</div>
            </div>
            <button id="themeToggle" class="theme-toggle">
                <i class="fas fa-moon"></i> Dark Mode
            </button>
        </div>

        <h2 class="header-title">Student Profile: <?php echo htmlspecialchars($student['fullname']); ?></h2>
        <?php if($msg) echo "<p style='color:green; background:var(--input-bg); padding:10px; border:1px solid var(--btn-primary); border-radius:4px;'>$msg</p>"; ?>

        <div class="split-view">
            
            <div>
                <div class="card">
                    <h3><i class="fas fa-chart-line"></i> Competency Progress</h3>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="studentChart"></canvas>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-history"></i> Evaluation History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Evaluator</th>
                                <th>Score</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history_data as $row): ?>
                            <tr>
                                <td><?php echo date("M d", strtotime($row['upload_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['evaluation_title'] ? $row['evaluation_title'] : 'General Eval'); ?></td>
                                <td>
                                    <?php if($row['evaluator_id'] == $admin_id): ?>
                                        <span style="font-weight:bold; color:#c0392b;">You (Admin)</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($row['evaluator_name']); ?>
                                        <br><small style="opacity:0.7;"><?php echo ucfirst($row['evaluator_role']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo $row['competency_score']; ?></strong>/10</td>
                                <td>
                                    <a href="admin_view_evaluation.php?id=<?php echo $row['id']; ?>" class="btn btn-view" style="padding: 5px 10px; font-size: 11px;">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="card" style="border-top: 5px solid #c0392b; position: sticky; top: 20px;">
                    <h3><i class="fas fa-gavel"></i> Admin General Evaluation</h3>
                    <p style="font-size:12px; opacity:0.7; margin-bottom:15px;">
                        Submit an official evaluation or observation that isn't tied to a specific portfolio file.
                    </p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        
                        <div class="form-group">
                            <label>Title / Activity Name</label>
                            <input type="text" name="eval_title" class="form-control" placeholder="e.g. Final Deliberation" required>
                        </div>

                        <div class="form-group">
                            <label>Competency Score (1-10)</label>
                            <input type="number" name="score" class="form-control" min="1" max="10" required>
                        </div>

                        <div class="form-group">
                            <label>Quick Feedback</label>
                            <select id="quickText" class="form-control" onchange="insertQuickText()">
                                <option value="">-- Insert Phrase --</option>
                                <option value="Student meets all competency requirements.">Requirements Met</option>
                                <option value="Outstanding performance in practical teaching.">Outstanding Practical</option>
                                <option value="Administrative requirements incomplete.">Admin Requirements</option>
                                <option value="Intervention recommended.">Intervention Needed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Notes / Feedback</label>
                            <textarea name="notes" id="notesArea" class="form-control" rows="6" placeholder="Enter detailed feedback..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Attach Rubric/File (Optional)</label>
                            <input type="file" name="file" class="form-control">
                        </div>

                        <button type="submit" class="btn-submit" style="background-color: #c0392b;">Submit Evaluation</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script src="js/script.js"></script>
    <script>
        const ctx = document.getElementById('studentChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Competency Score (Accepted)',
                    data: <?php echo json_encode($scores); ?>,
                    borderColor: '#c0392b', 
                    backgroundColor: 'rgba(192, 57, 43, 0.2)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 10 } }
            }
        });
    </script>

</body>
</html>