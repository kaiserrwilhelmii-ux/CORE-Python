<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
// Redirect Admin/Supervisor
if ($_SESSION['role'] == 'admin') { header("Location: admin_dashboard.php"); exit(); }
if ($_SESSION['role'] == 'supervisor') { header("Location: supervisor_dashboard.php"); exit(); }

$user_id = $_SESSION['user_id'];

// Graph Data (Accepted Only)
$dates = [];
$scores = [];
$graph_q = $conn->query("SELECT upload_date, competency_score FROM evaluations WHERE user_id = $user_id AND status='accepted' ORDER BY upload_date ASC");

while($row = $graph_q->fetch_assoc()) {
    $dates[] = date("M d", strtotime($row['upload_date']));
    $scores[] = $row['competency_score'];
}

// Pending Alerts
$pending_q = $conn->query("SELECT COUNT(*) as count FROM evaluations WHERE user_id = $user_id AND status='pending'");
$pending_count = $pending_q->fetch_assoc()['count'];

// Recent Portfolio
$portfolio_q = $conn->query("SELECT * FROM submissions WHERE user_id = $user_id ORDER BY upload_date DESC LIMIT 3");
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } }
        
        .sub-item { padding: 10px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
        .sub-item:last-child { border-bottom: none; }
        
        .badge-grad { background: #27ae60; padding: 3px 8px; border-radius: 10px; color: white; font-size: 10px; }
        .badge-pend { background: #f39c12; padding: 3px 8px; border-radius: 10px; color: white; font-size: 10px; }
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

        <?php if($pending_count > 0): ?>
        <div class="card" style="background:#fff3cd; border-color:#ffeeba; color:#856404; margin-bottom:20px; border-left:5px solid #ffc107;">
            <strong><i class="fas fa-exclamation-triangle"></i> Action Required:</strong>
            You have <strong><?php echo $pending_count; ?></strong> evaluation(s) waiting for your acceptance.
            <a href="user_evaluations.php" style="text-decoration:underline; color:#856404; font-weight:bold; margin-left:10px;">View Now</a>
        </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            
            <div class="card">
                <h3><i class="fas fa-chart-area"></i> Competency Progress</h3>
                <p style="font-size:12px; opacity:0.7; margin-bottom:10px;">Scores from accepted evaluations over time.</p>
                <div style="height: 300px; width: 100%;">
                    <canvas id="scoreChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-folder-open"></i> Recent Submissions</h3>
                <p style="font-size:12px; opacity:0.7; margin-bottom:15px;">Your uploaded evidence files.</p>
                
                <?php if($portfolio_q->num_rows > 0): ?>
                    <?php while($sub = $portfolio_q->fetch_assoc()): ?>
                    <div class="sub-item">
                        <div>
                            <strong><?php echo htmlspecialchars($sub['title']); ?></strong><br>
                            <small style="opacity:0.7;"><?php echo date("M d", strtotime($sub['upload_date'])); ?></small>
                        </div>
                        <div>
                            <?php if($sub['status'] == 'evaluated'): ?>
                                <span class="badge-grad">Graded</span>
                            <?php else: ?>
                                <span class="badge-pend">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <a href="user_evaluations.php" class="btn btn-view" style="display:block; text-align:center; margin-top:15px;">View All</a>                <?php else: ?>
                    <p style="text-align:center; opacity:0.6; padding:20px;">No files uploaded yet.</p>
                    <a href="student_portfolio.php" class="btn btn-edit" style="display:block; text-align:center;">Upload Evidence</a>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script src="js/script.js"></script>
    <script>
        const ctx = document.getElementById('scoreChart').getContext('2d');
        const scoreChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Competency Score',
                    data: <?php echo json_encode($scores); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
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