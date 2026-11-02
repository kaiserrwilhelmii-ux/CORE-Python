<?php
session_start();
include 'db_connect.php';

// Security: Only Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = "";
$error = "";

// Fetch Existing Evaluation Data
$eval_q = $conn->query("SELECT e.*, u.fullname as student_name FROM evaluations e JOIN users u ON e.user_id = u.id WHERE e.id=$id");
if($eval_q->num_rows == 0) { header("Location: admin_evaluations.php"); exit(); }
$eval = $eval_q->fetch_assoc();

// Handle Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eval_title = $_POST['eval_title'];
    $score = $_POST['score'];
    $notes = $_POST['notes'];
    
    $file_path = $eval['file_path']; 
    if (!empty($_FILES['file']['name'])) {
        $target_dir = "uploads/";
        $new_file_name = "admin_edit_" . time() . "_" . basename($_FILES["file"]["name"]);
        $target_file = $target_dir . $new_file_name;
        
        if(move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            $file_path = $target_file;
        }
    }

    $stmt = $conn->prepare("UPDATE evaluations SET evaluation_title=?, competency_score=?, readiness_notes=?, file_path=? WHERE id=?");
    $stmt->bind_param("sisii", $eval_title, $score, $notes, $file_path, $id);
    
    if($stmt->execute()) {
        $msg = "Evaluation updated successfully!";
        $eval = $conn->query("SELECT e.*, u.fullname as student_name FROM evaluations e JOIN users u ON e.user_id = u.id WHERE e.id=$id")->fetch_assoc();
    } else {
        $error = "Database Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Evaluation</title>
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

        <h2 class="header-title">Edit Evaluation Record</h2>
        <?php if($msg) echo "<p style='color:green; background:var(--input-bg); padding:10px; border:1px solid #2ecc71; border-radius:4px;'>$msg</p>"; ?>
        <?php if($error) echo "<p style='color:red; background:var(--input-bg); padding:10px; border:1px solid #e74c3c; border-radius:4px;'>$error</p>"; ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label>Student Name (Read Only)</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($eval['student_name']); ?>" readonly style="opacity: 0.7;">
                </div>

                <div class="form-group">
                    <label>Evaluation Title</label>
                    <input type="text" name="eval_title" class="form-control" value="<?php echo htmlspecialchars($eval['evaluation_title']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Competency Score (1-10)</label>
                    <input type="number" name="score" class="form-control" min="1" max="10" value="<?php echo $eval['competency_score']; ?>" required>
                </div>

                <div class="form-group">
                    <label>Readiness Notes / Feedback</label>
                    <textarea name="notes" class="form-control" rows="6"><?php echo htmlspecialchars($eval['readiness_notes']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Attached Document</label>
                    <?php if(!empty($eval['file_path'])): ?>
                        <div style="margin-bottom:10px; font-size:13px; color:var(--text-color);">
                            Current File: <a href="<?php echo $eval['file_path']; ?>" target="_blank" style="color:var(--btn-primary);">View Current Document</a>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom:10px; font-size:13px; color:var(--text-color); opacity:0.7;">No file attached.</div>
                    <?php endif; ?>
                    
                    <label style="font-size:12px; margin-top:5px; display:block;">Upload new file to replace current (Optional):</label>
                    <input type="file" name="file" class="form-control">
                </div>

                <button type="submit" class="btn-submit">Update Record</button>
            </form>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>