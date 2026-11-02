<?php
session_start();
include __DIR__ . '/db_connect.php';

// Allows BOTH Admins and Supervisors to view the Master Scorecard Directory
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'supervisor'])) { 
    header("Location: index.php"); exit(); 
}
$viewer_role = $_SESSION['role'];
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Scorecards</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { display: flex; height: 100vh; overflow: hidden; margin: 0; background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; box-sizing: border-box; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px; }
        .table-responsive { overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f8f9fa; color: #2c3e50; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; font-size: 12px; }
        tr:hover { background: #fdfdfd; }
        .btn-view { background: #3498db; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 12px; display: inline-block; transition: 0.2s; }
        .btn-view:hover { background: #2980b9; }
        .btn-eval { background: #e67e22; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 12px; display: inline-block; transition: 0.2s; }
        .btn-eval:hover { background: #d35400; }
        .btn-back { background: #95a5a6; color: white; padding: 10px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 13px; display: inline-block; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php if ($student_id == 0): ?>
            
            <div class="card">
                <h3>Select a Student to view their Scorecard</h3>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th>Student Name</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                        <?php
                        $students = $conn->query("SELECT * FROM users WHERE role='student_teacher' ORDER BY fullname ASC");
                        if ($students->num_rows > 0):
                            while($s = $students->fetch_assoc()):
                        ?>
                            <tr>
                                <td><i class="fas fa-user-circle" style="color:#7f8c8d; margin-right:8px;"></i> <strong><?php echo htmlspecialchars($s['fullname']); ?></strong></td>
                                <td><span style="background:#e8f4f8; color:#2980b9; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold;">Student Teacher</span></td>
                                <td><a href="?student_id=<?php echo $s['id']; ?>" class="btn-view"><i class="fas fa-id-card"></i> View Scorecard</a></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="3" style="text-align:center; color:#7f8c8d;">No students found in the system.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <?php
            $stu_q = $conn->query("SELECT fullname FROM users WHERE id=$student_id");
            $stu_name = ($stu_q->num_rows > 0) ? $stu_q->fetch_assoc()['fullname'] : "Unknown Student";
            ?>
            <a href="admin_evaluations.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Directory</a>
            
            <div class="top-header" style="background:#fff; padding:15px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05); border-left: 5px solid #3498db;">
                <h2 style="margin:0;"><i class="fas fa-id-card"></i> Scorecard: <?php echo htmlspecialchars($stu_name); ?></h2>
            </div>

            <div class="card" style="border-top: 3px solid #e67e22;">
                <h3 style="color:#d35400; margin-top:0;"><i class="fas fa-clock"></i> Action Required: Pending Submissions</h3>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th>Date Submitted</th>
                            <th>Submission Title</th>
                            <th>Action</th>
                        </tr>
                        <?php
                        $pending = $conn->query("SELECT * FROM submissions WHERE user_id=$student_id AND status='pending' ORDER BY created_at DESC");
                        if ($pending->num_rows > 0):
                            while($p = $pending->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo date("M d, Y", strtotime($p['created_at'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                                <td>
                                    <?php if ($viewer_role === 'admin'): ?>
                                        <a href="admin_evaluate_submission.php?sub_id=<?php echo $p['id']; ?>" class="btn-eval"><i class="fas fa-pencil-alt"></i> Evaluate</a>
                                    <?php else: ?>
                                        <a href="supervisor_evaluate_submission.php?sub_id=<?php echo $p['id']; ?>" class="btn-eval"><i class="fas fa-pencil-alt"></i> Evaluate</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="3" style="text-align:center; color:#7f8c8d;">This student has no pending submissions.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <div class="card" style="border-top: 3px solid #27ae60;">
                <h3 style="color:#27ae60; margin-top:0;"><i class="fas fa-check-circle"></i> Official Grades & History</h3>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th>Date Graded</th>
                            <th>Evaluation Title</th>
                            <th>Score</th>
                            <th>Action</th>
                        </tr>
                        <?php
                        $completed = $conn->query("SELECT * FROM evaluations WHERE user_id=$student_id ORDER BY upload_date DESC");
                        if ($completed->num_rows > 0):
                            while($c = $completed->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo date("M d, Y", strtotime($c['upload_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($c['evaluation_title']); ?></strong></td>
                                <td><span style="font-weight:bold; color:#27ae60; font-size:16px;"><?php echo htmlspecialchars($c['competency_score']); ?></span><span style="color:#7f8c8d;">/100</span></td>
                                <td><a href="admin_view_evaluation.php?id=<?php echo $c['id']; ?>" class="btn-view" style="background:#2c3e50;"><i class="fas fa-eye"></i> View Record</a></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" style="text-align:center; color:#7f8c8d;">No grades have been recorded yet.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
