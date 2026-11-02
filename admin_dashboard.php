<?php
// TEMPORARY DEBUGGING: Force all fatal errors to print to the screen
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db_connect.php';

// 2. Loop-Breaker: If they aren't an admin, completely destroy the session before kicking them out
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    session_unset();
    session_destroy();
    header("Location: index.php"); 
    exit(); 
}

// --- STATISTICS ---
$q1 = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='student_teacher'");
$total_students = $q1 ? ($q1->fetch_assoc()['count'] ?? 0) : 0;

$q2 = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='supervisor'");
$total_supervisors = $q2 ? ($q2->fetch_assoc()['count'] ?? 0) : 0;

// --- INBOX: Pending Submissions ---
$sql_subs = "SELECT s.*, u.fullname, u.assigned_supervisor_id 
             FROM submissions s 
             JOIN users u ON s.user_id = u.id 
             WHERE s.status = 'pending' 
             ORDER BY s.upload_date ASC";
$pending_subs = $conn->query($sql_subs);

// --- NEW ALERT: Password Reset Requests ---
$reset_q = $conn->query("SELECT COUNT(*) as count FROM users WHERE reset_request = 1");
$reset_count = $reset_q ? ($reset_q->fetch_assoc()['count'] ?? 0) : 0;

// --- STANDINGS ---
$sql_students = "SELECT u.id, u.fullname, u.course, u.year_level,
                 COALESCE((SELECT AVG(competency_score) FROM evaluations WHERE user_id = u.id AND status='accepted'), 0) as avg_score
                 FROM users u 
                 WHERE u.role='student_teacher'
                 ORDER BY avg_score DESC LIMIT 5";
$students = $conn->query($sql_students);

// --- SUPERVISOR LIST ---
$sql_supervisors = "SELECT s.*, 
                    (SELECT COUNT(*) FROM users WHERE assigned_supervisor_id = s.id) as student_count 
                    FROM users s 
                    WHERE s.role='supervisor' 
                    ORDER BY s.fullname ASC";
$supervisors = $conn->query($sql_supervisors);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: var(--card-shadow); border-left: 5px solid; }
        .stat-card h3 { margin: 0; font-size: 28px; color: var(--text-color); }
        .stat-card p { color: var(--text-color); opacity: 0.7; margin: 5px 0 0; }
        
        .dashboard-split { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        @media (max-width: 1000px) { .dashboard-split { grid-template-columns: 1fr; } }
        
        .score-badge, .badge { padding: 4px 8px; border-radius: 12px; font-weight: bold; font-size: 11px; color: white; display: inline-block; }
        .bg-green { background: #27ae60; }
        .bg-yellow { background: #f1c40f; color: #333; }
        .bg-red { background: #e74c3c; }
        .bg-grey { background: #bdc3c7; }
    </style>
</head>
<body>

    <!-- Main Left Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Container (Wraps the ENTIRE page) -->
    <div class="main-content">

        <?php if($reset_count > 0): ?>
        <div class="card" style="background: #fff5f5; border-left: 5px solid #e74c3c; border-radius: 8px; margin-bottom: 25px; display:flex; justify-content:space-between; align-items:center;">
            <div style="color: #c0392b;">
                <h3 style="margin:0; font-size:18px;"><i class="fas fa-key"></i> Password Reset Requests</h3>
                <p style="margin:5px 0 0;">There are <strong><?php echo $reset_count; ?></strong> users requesting a password reset.</p>
            </div>
            <a href="admin_manage.php?filter=reset" class="btn btn-remove" style="padding:10px 15px; font-size:14px;">Review Requests</a>
        </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="border-color: #3498db;">
                <h3><?php echo $total_students; ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-card" style="border-color: #9b59b6;">
                <h3><?php echo $total_supervisors; ?></h3>
                <p>Total Supervisors</p>
            </div>
            <div class="stat-card" style="border-color: #e74c3c;">
                <h3><?php echo $pending_subs ? $pending_subs->num_rows : 0; ?></h3>
                <p>Pending Submissions</p>
            </div>
        </div>

        <!-- Dashboard Split Grid -->
        <div class="dashboard-split">
            
            <div class="card" style="border-top: 4px solid #c0392b;">
                <h3><i class="fas fa-inbox"></i> Pending Submissions</h3>
                <?php if ($pending_subs && $pending_subs->num_rows > 0): ?>
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
                                <td><strong><?php echo htmlspecialchars($sub['fullname'] ?? 'Unknown'); ?></strong></td>
                                <td><?php echo htmlspecialchars($sub['title'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="admin_evaluate_submission.php?sub_id=<?php echo $sub['id']; ?>" class="btn btn-edit" style="font-size:12px; padding:5px 10px;">Grade</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="padding:20px; color:var(--text-color); opacity:0.7; text-align:center;">No pending submissions.</p>
                <?php endif; ?>
            </div>

            <div class="card" style="border-top: 4px solid #2980b9;">
                <h3><i class="fas fa-chart-line"></i> Top Performing Students</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th>Avg</th>
                            <th>Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($students && $students->num_rows > 0):
                            $rank = 1;
                            while($row = $students->fetch_assoc()): 
                                $avg = round($row['avg_score'] ?? 0, 1);
                                $badge = ($avg >= 8) ? 'bg-green' : (($avg >= 5) ? 'bg-yellow' : (($avg > 0) ? 'bg-red' : 'bg-grey'));
                        ?>
                        <tr>
                            <td style="font-weight:bold; opacity:0.6;">#<?php echo $rank++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['fullname'] ?? 'Unknown'); ?></strong><br>
                                <small style="opacity:0.7;"><?php echo htmlspecialchars($row['course'] ?? 'N/A'); ?></small>
                            </td>
                            <td>
                                <span class="score-badge <?php echo $badge; ?>">
                                    <?php echo ($avg > 0) ? $avg : 'N/A'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="admin_view_student.php?id=<?php echo $row['id']; ?>" class="btn btn-view" style="font-size:12px;"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; 
                        else: ?>
                            <tr><td colspan="4" style="text-align:center; padding:20px; opacity:0.7;">No evaluations yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Supervisors List -->
        <div class="card" style="border-top: 4px solid #8e44ad;">
            <h3><i class="fas fa-chalkboard-teacher"></i> Registered Cooperating Teachers (Supervisors)</h3>
            <?php if ($supervisors && $supervisors->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Supervisor Name</th>
                        <th>Email</th>
                        <th>Assigned Students</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sup = $supervisors->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sup['fullname'] ?? 'Unknown'); ?></strong></td>
                        <td><?php echo htmlspecialchars($sup['email'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if(($sup['student_count'] ?? 0) > 0): ?>
                                <span class="badge" style="background:#2ecc71;">
                                    <?php echo $sup['student_count']; ?> Students
                                </span>
                            <?php else: ?>
                                <span style="opacity:0.5; font-size:12px;">No assignments</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $sup['id']; ?>" class="btn btn-edit" style="font-size:12px; padding:5px 10px;">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:20px; text-align:center; opacity:0.7;">No supervisors found.</p>
            <?php endif; ?>
        </div>

    </div> <!-- Properly closes .main-content here at the end -->

</body>
</html>
