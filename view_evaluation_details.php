<?php
session_start();
include 'db_connect.php';


$eval_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$my_role = $_SESSION['role'];

$sql = "SELECT e.*, 
               sender.fullname as sender_name, 
               sender.role as sender_role 
        FROM evaluations e 
        LEFT JOIN users sender ON e.evaluator_id = sender.id 
        WHERE e.id = ? AND (e.user_id = ? OR e.evaluator_id = ?)";

$stmt = $conn->prepare($sql);
// Note: We bind user_id twice because of the OR condition
$stmt->bind_param("iii", $eval_id, $user_id, $user_id); 
$stmt->execute();
$result = $stmt->get_result();

// ... rest of the code ...

if ($result->num_rows == 0) {
    die("Evaluation not found or access denied.");
}

$eval = $result->fetch_assoc();
$file_ext = strtolower(pathinfo($eval['file_path'], PATHINFO_EXTENSION));
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
        .document-panel { background: #525659; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative; }
        
        .sender-box { background: var(--input-bg); border-left: 4px solid var(--btn-primary); padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid var(--border-color); }
        .score-box { text-align: center; padding: 20px; background: var(--input-bg); border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px; }
        .score-val { font-size: 40px; font-weight: bold; display: block; color: var(--text-color); }
        
        .feedback-text { font-size: 15px; line-height: 1.6; color: var(--text-color); white-space: pre-wrap; background: var(--input-bg); border: 1px solid var(--border-color); padding: 15px; border-radius: 4px; }
        
        .action-area { margin-top: 30px; border-top: 2px solid var(--border-color); padding-top: 20px; text-align: center; }

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

        <h2 class="header-title" style="margin-bottom: 15px;">Evaluation Details</h2>

        <div class="split-layout">
            
            <div class="details-panel">
                
                <div class="sender-box">
                    <strong style="color:var(--text-color); display:block; margin-bottom:5px;">Evaluated By:</strong>
                    <?php if($eval['sender_name']): ?>
                        <span style="font-size:16px; font-weight:bold; color:var(--text-color);"><?php echo htmlspecialchars($eval['sender_name']); ?></span>
                        <br>
                        <span style="display:inline-block; margin-top:5px; font-size:11px; padding:3px 8px; background:var(--btn-primary); color:white; border-radius:4px;">
                            <?php echo strtoupper($eval['sender_role']); ?>
                        </span>
                    <?php else: ?>
                        <span style="color:var(--text-color); opacity:0.7;">System Administrator</span>
                    <?php endif; ?>
                    <div style="margin-top:10px; font-size:12px; opacity:0.7;">
                        Date: <?php echo date("F d, Y", strtotime($eval['upload_date'])); ?>
                    </div>
                </div>

                <div class="score-box">
                    <span style="font-weight:bold; opacity:0.8;">Competency Score</span>
                    <span class="score-val"><?php echo $eval['competency_score']; ?>/10</span>
                    <?php if($eval['status'] == 'accepted'): ?>
                        <div style="margin-top:10px; color:#27ae60; font-weight:bold;">
                            <i class="fas fa-check-circle"></i> Acknowledged
                        </div>
                    <?php else: ?>
                        <div style="margin-top:10px; color:#e67e22; font-weight:bold;">
                            <i class="fas fa-clock"></i> Pending Review
                        </div>
                    <?php endif; ?>
                </div>

                <h4 style="border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-bottom: 10px;">
                    <i class="fas fa-comment-dots"></i> Feedback / Notes
                </h4>
                <div class="feedback-text">
                    <?php if($eval['evaluation_title']): ?>
                        <strong style="display:block; margin-bottom:10px;"><?php echo htmlspecialchars($eval['evaluation_title']); ?></strong>
                    <?php endif; ?>
                    <?php echo $eval['readiness_notes'] ? htmlspecialchars($eval['readiness_notes']) : "No written feedback provided."; ?>
                </div>

                <?php if($eval['status'] == 'pending'): ?>
                <div class="action-area">
                    <p style="font-size:13px; opacity:0.7; margin-bottom:10px;">By clicking below, you acknowledge receiving this evaluation.</p>
                    <form method="POST" action="user_evaluations.php">
                        <input type="hidden" name="accept_id" value="<?php echo $eval['id']; ?>">
                        <button type="submit" class="btn-submit" style="background:#2ecc71; font-weight:bold; font-size:16px;">
                            <i class="fas fa-check-circle"></i> Acknowledge & Accept
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <div class="document-panel">
                <?php if($file_ext == 'pdf'): ?>
                    <iframe src="<?php echo $eval['file_path']; ?>" width="100%" height="100%" style="border:none;"></iframe>
                <?php else: ?>
                    <div style="text-align:center; color:white;">
                        <i class="fas fa-file-alt" style="font-size: 60px; margin-bottom:20px; display:block;"></i>
                        <p>Preview not available for this file type.</p>
                        <a href="<?php echo $eval['file_path']; ?>" class="btn btn-view" style="background:white; color:#333; display:inline-block; margin-top:10px;">
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