<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') { header("Location: index.php"); exit(); }

$supervisor_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = "";

// 1. Verify Access (Security)
$check_assign = $conn->query("SELECT * FROM users WHERE id=$student_id AND assigned_supervisor_id=$supervisor_id");
if ($check_assign->num_rows == 0) {
    die("Access Denied: This student is not assigned to you.");
}
$student = $check_assign->fetch_assoc();

// 2. Handle New General Evaluation Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eval_title = $_POST['eval_title'];
    $score = $_POST['score'];
    $notes = $_POST['notes'];
    
    // File Upload (Optional attachment from Supervisor)
    $target_dir = "uploads/";
    $target_file = "";
    if (!empty($_FILES['file']['name'])) {
        $file_name = basename($_FILES["file"]["name"]);
        $target_file = $target_dir . $student_id . "_sup_eval_" . time() . "_" . $file_name;
        move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
    }

    $stmt = $conn->prepare("INSERT INTO evaluations (user_id, evaluator_id, evaluation_title, competency_score, readiness_notes, file_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iisiss", $student_id, $supervisor_id, $eval_title, $score, $notes, $target_file);
    
    if($stmt->execute()) {
        $msg = "General evaluation submitted successfully!";
    } else {
        $msg = "Database Error.";
    }
}

$history_q = $conn->query("SELECT * FROM evaluations WHERE user_id = $student_id ORDER BY upload_date ASC");
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
    <title>Student Progress: <?php echo htmlspecialchars($student['fullname']); ?></title>
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

        <h2 class="header-title">Student: <?php echo htmlspecialchars($student['fullname']); ?></h2>
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
                                <th>Score</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history_data as $row): ?>
                            <tr>
                                <td><?php echo date("M d", strtotime($row['upload_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['evaluation_title'] ? $row['evaluation_title'] : 'General Eval'); ?></td>
                                <td><strong><?php echo $row['competency_score']; ?></strong>/10</td>
                                <td>
                                    <?php if($row['status'] == 'accepted'): ?>
                                        <span class="badge" style="background:#27ae60; color:white; padding:4px 8px; border-radius:4px; font-size:11px;">Accepted</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f39c12; color:white; padding:4px 8px; border-radius:4px; font-size:11px;">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="supervisor_view_evaluation.php?id=<?php echo $row['id']; ?>" class="btn btn-view" style="padding: 5px 10px; font-size: 11px;">
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
                <div class="card" style="border-top: 5px solid #3498db; position: sticky; top: 20px;">
                    <h3><i class="fas fa-pen-nib"></i> General Evaluation</h3>
                    <p style="font-size:12px; opacity:0.7; margin-bottom:15px;">Use this form for classroom observations, attendance, or general performance (Not tied to a specific file submission).</p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        
                        <div class="form-group">
                            <label>Title / Activity Name</label>
                            <input type="text" name="eval_title" class="form-control" placeholder="e.g. Classroom Observation 1" required>
                        </div>

                        <div class="form-group">
                            <label>Competency Score (1-10)</label>
                            <input type="number" name="score" class="form-control" min="1" max="10" required>
                        </div>

                        <div class="form-group">
                            <label>Quick Feedback</label>
                            <select id="quickText" class="form-control" onchange="insertQuickText()">
                                <option value="">-- Insert Phrase --</option>
                                <option value="Student demonstrated excellent classroom management.">Classroom Management</option>
                                <option value="Lesson objectives were clearly met.">Objectives Met</option>
                                <option value="Needs to work on voice projection and confidence.">Voice/Confidence</option>
                                <option value="Punctual and professional appearance.">Professionalism</option>
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

                        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Evaluation</button>
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
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
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