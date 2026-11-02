<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') { header("Location: index.php"); exit(); }

$supervisor_id = $_SESSION['user_id'];

$count_q = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='student_teacher' AND assigned_supervisor_id = $supervisor_id");
$total_students = $count_q->fetch_assoc()['total'];

$pending_evals_q = $conn->query("SELECT COUNT(*) as count FROM evaluations e JOIN users u ON e.user_id = u.id WHERE u.assigned_supervisor_id = $supervisor_id AND e.status='pending'");
$pending_count = $pending_evals_q->fetch_assoc()['count'];

$sql_subs = "SELECT s.*, u.fullname FROM submissions s JOIN users u ON s.user_id = u.id WHERE u.assigned_supervisor_id = $supervisor_id AND s.status = 'pending' ORDER BY s.upload_date ASC";
$pending_subs = $conn->query($sql_subs);

$sql_students = "SELECT u.id, u.fullname, u.email, u.course,
                 COALESCE((SELECT AVG(competency_score) FROM evaluations WHERE user_id = u.id AND status='accepted'), 0) as avg_score
                 FROM users u 
                 WHERE u.role='student_teacher' AND u.assigned_supervisor_id = $supervisor_id
                 ORDER BY avg_score DESC";
$students = $conn->query($sql_students);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supervisor Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .stat-box { background: var(--card-bg); padding: 20px; border-radius: 8px; border-left: 5px solid; box-shadow: var(--card-shadow); }
        .stat-box h2 { margin: 0; color: var(--text-color); font-size: 28px; }
        .stat-box small { color: var(--text-color); opacity: 0.7; text-transform: uppercase; }
        
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } }
        
        .score-badge { padding: 4px 8px; border-radius: 12px; font-weight: bold; font-size: 11px; color: white; }
        .bg-green { background: #27ae60; }
        .bg-yellow { background: #f1c40f; color: #333; }
        .bg-red { background: #e74c3c; }
        .bg-grey { background: #bdc3c7; }
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
        
        <div class="stats-row">
            <div class="stat-box" style="border-color:#3498db;">
                <h2><?php echo $total_students; ?></h2>
                <small>Assigned Students</small>
            </div>
            <div class="stat-box" style="border-color:#f39c12;">
                <h2><?php echo $pending_count; ?></h2>
                <small>Unacknowledged Evaluations</small>
            </div>
        </div>

        <div class="card" style="margin-bottom: 25px; border-left: 5px solid #e67e22;">
            <h3><i class="fas fa-inbox"></i> Inbox: Submissions to Grade</h3>
            <?php if ($pending_subs->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Title</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($sub = $pending_subs->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sub['fullname']); ?></strong></td>
                            <td><?php echo htmlspecialchars($sub['title']); ?></td>
                            <td>
                                <a href="supervisor_evaluate_submission.php?sub_id=<?php echo $sub['id']; ?>" class="btn btn-edit" style="font-size:12px; padding:5px 10px;">Grade</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding:15px; color:var(--text-color); opacity:0.7;">No pending submissions.</p>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid">
            <div class="card" style="border-top: 4px solid #2980b9;">
                <h3><i class="fas fa-chart-bar"></i> My Students' Standing</h3>
                <p style="font-size:12px; color:var(--text-color); opacity:0.7;">Avg of Accepted Evaluations</p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Avg</th>
                            <th>Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($students->num_rows > 0):
                            while($row = $students->fetch_assoc()): 
                                $avg = round($row['avg_score'], 1);
                                $badge = ($avg >= 8) ? 'bg-green' : (($avg >= 5) ? 'bg-yellow' : (($avg > 0) ? 'bg-red' : 'bg-grey'));
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['fullname']); ?></strong><br>
                                <small style="opacity:0.7;"><?php echo htmlspecialchars($row['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['course']); ?></td>
                            <td>
                                <span class="score-badge <?php echo $badge; ?>">
                                    <?php echo ($avg > 0) ? $avg : 'N/A'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="supervisor_view_student.php?id=<?php echo $row['id']; ?>" class="btn btn-view" style="font-size:12px;">View</a>
                            </td>
                        </tr>
                        <?php endwhile; 
                        else: ?>
                            <tr><td colspan="4" style="text-align:center; padding:20px; opacity:0.7;">No students assigned yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> Quick Tips</h3>
                <ul style="padding-left:20px; color:var(--text-color); opacity:0.8; font-size:14px; line-height:1.6;">
                    <li>Scores only appear in standings after the student <strong>accepts</strong> the evaluation.</li>
                    <li>Pending submissions in your Inbox need grading.</li>
                    <li>Click "View" to see a student's full history and graph.</li>
                </ul>
            </div>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>