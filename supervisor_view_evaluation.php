<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') { header("Location: index.php"); exit(); }

$eval_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$supervisor_id = $_SESSION['user_id'];

// Fetch Detail + Verify Access
$sql = "SELECT e.*, 
               student.fullname as student_name, 
               student.assigned_supervisor_id,
               evaluator.fullname as evaluator_name, 
               evaluator.role as evaluator_role 
        FROM evaluations e 
        JOIN users student ON e.user_id = student.id 
        LEFT JOIN users evaluator ON e.evaluator_id = evaluator.id 
        WHERE e.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eval_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) { die("Evaluation not found."); }

$eval = $result->fetch_assoc();
$file_ext = strtolower(pathinfo($eval['file_path'], PATHINFO_EXTENSION));

// Security Check: Ensure this supervisor is related to the student OR is the one who wrote it
if($eval['assigned_supervisor_id'] != $supervisor_id && $eval['evaluator_id'] != $supervisor_id) {
    die("Access Denied: You are not assigned to this student.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Evaluation Details</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .split-layout { display: grid; grid-template-columns: 350px 1fr; gap: 20px; height: calc(100vh - 180px); }
        .details-panel { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: var(--card-shadow); overflow-y: auto; }
        .document-panel { background: #525659; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        
        .info-box { background: var(--input-bg); border-left: 4px solid var(--btn-primary); padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid var(--border-color); }
        .info-box strong { color: var(--text-color); }
        .info-box small { color: var(--text-color); opacity: 0.7; }
        
        .score-val { font-size: 32px; font-weight: bold; color: var(--text-color); }
        .feedback-text { white-space: pre-wrap; background: var(--input-bg); border: 1px solid var(--border-color); padding: 15px; border-radius: 4px; margin-top: 10px; color: var(--text-color); line-height: 1.6; }
        
        @media (max-width: 900px) { .split-layout { grid-template-columns: 1fr; height: auto; } .document-panel { height: 500px; } }
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

        <h2 class="header-title">Evaluation Details</h2>

        <div class="split-layout">
            
            <div class="details-panel">
                
                <div class="info-box">
                    <strong>Student:</strong><br>
                    <span style="font-size:18px; font-weight:bold;"><?php echo htmlspecialchars($eval['student_name']); ?></span>
                </div>

                <div class="info-box" style="border-left-color: #2c3e50;">
                    <strong>Submitted By:</strong><br>
                    <?php if($eval['evaluator_id'] == $supervisor_id): ?>
                        <span style="font-weight:bold; color:var(--btn-primary);">You (Me)</span>
                    <?php else: ?>
                        <strong><?php echo htmlspecialchars($eval['evaluator_name']); ?></strong> (<?php echo ucfirst($eval['evaluator_role']); ?>)
                    <?php endif; ?>
                    <br>
                    <small>Date: <?php echo date("M d, Y", strtotime($eval['upload_date'])); ?></small>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="font-weight:bold; color:var(--text-color);">Score Given:</label>
                    <div class="score-val">
                        <?php echo $eval['competency_score']; ?>/10
                    </div>
                    
                    <div style="margin-top:5px;">
                        <?php if($eval['status'] == 'accepted'): ?>
                            <span class="badge" style="background:#2ecc71; color:white; padding:4px 8px; border-radius:4px; font-size:11px;">
                                <i class="fas fa-check-circle"></i> Acknowledged by Student
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background:#f39c12; color:white; padding:4px 8px; border-radius:4px; font-size:11px;">
                                <i class="fas fa-clock"></i> Pending Acknowledgment
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <label style="font-weight:bold; color:var(--text-color);">Feedback Notes:</label>
                <div class="feedback-text">
                    <?php if($eval['evaluation_title']): ?>
                        <strong style="display:block; margin-bottom:10px;"><?php echo htmlspecialchars($eval['evaluation_title']); ?></strong>
                    <?php endif; ?>
                    <?php echo $eval['readiness_notes'] ? htmlspecialchars($eval['readiness_notes']) : "No notes provided."; ?>
                </div>
            </div>

            <div class="document-panel">
                <?php if($file_ext == 'pdf'): ?>
                    <iframe src="<?php echo $eval['file_path']; ?>" width="100%" height="100%" style="border:none;"></iframe>
                <?php else: ?>
                    <div style="text-align:center; color:white;">
                        <i class="fas fa-file-alt" style="font-size: 60px; margin-bottom:20px; display:block;"></i>
                        <p>Preview not available.</p>
                        <a href="<?php echo $eval['file_path']; ?>" class="btn btn-view" style="background:white; color:#333; display:inline-block;">
                            <i class="fas fa-download"></i> Download File
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>