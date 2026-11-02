<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$user_id = $_SESSION['user_id'];
$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accept_id'])) {
    $eval_id = intval($_POST['accept_id']);
    $stmt = $conn->prepare("UPDATE evaluations SET status='accepted' WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $eval_id, $user_id);
    if($stmt->execute()) { $msg = "You have successfully acknowledged the evaluation."; }
}

$result = $conn->query("SELECT * FROM evaluations WHERE user_id = $user_id ORDER BY upload_date DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Evaluations</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        <h2 class="header-title">My Performance Evaluations</h2>
        <?php if($msg) echo "<p style='color:green;'>$msg</p>"; ?>

        <div class="card">
            <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Score</th>
                        <th>Supervisor Notes</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date("M d, Y", strtotime($row['upload_date'])); ?></td>
                        <td><strong><?php echo $row['competency_score']; ?></strong>/10</td>
                        <td><?php echo htmlspecialchars(substr($row['readiness_notes'], 0, 50)) . '...'; ?></td>
                        <td>
                            <?php if($row['status'] == 'pending'): ?>
                                <span class="badge" style="background:#f39c12; color:white; padding:4px 8px; border-radius:4px;">Action Required</span>
                            <?php else: ?>
                                <span class="badge" style="background:#27ae60; color:white; padding:4px 8px; border-radius:4px;">Acknowledged</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view_evaluation_details.php?id=<?php echo $row['id']; ?>" class="btn btn-view" style="padding:6px 12px;">View Details</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:20px; text-align:center; opacity:0.7;">No evaluations received yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
