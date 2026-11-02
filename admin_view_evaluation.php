<?php
session_start();
include __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$eval_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$query = "
    SELECT 
        e.*, 
        s.title AS sub_title, 
        s.description AS sub_desc, 
        s.file_path AS sub_file, 
        u.fullname AS student_name,
        ev.fullname AS evaluator_name
    FROM evaluations e
    LEFT JOIN submissions s ON e.submission_id = s.id
    JOIN users u ON e.user_id = u.id
    JOIN users ev ON e.evaluator_id = ev.id
    WHERE e.id = $eval_id
";

$result = $conn->query($query);
if($result->num_rows == 0) { echo "Evaluation not found."; exit(); }
$data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Evaluation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .gradient-header {
            padding: 20px;
            border-radius: 12px 12px 0 0;
            color: white;
            margin: -25px -25px 25px -25px;
        }
        .gh-blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .gh-green { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        
        .info-box {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #eee;
        }
        .dark-mode .info-box { background: #2d2d2d; border-color: #404040; }

        .score-display {
            font-size: 42px; font-weight: 800; color: #27ae60;
        }
        .score-total { font-size: 18px; color: #888; font-weight: normal; }
        
        .readable-text-box {
            background: #f9f9f9;
            color: #333; 
            font-size: 16px; 
            line-height: 1.6;
            padding: 25px;
            min-height: 180px;
            border: 1px solid #eee;
            border-radius: 12px;
            white-space: pre-wrap;
        }
        .dark-mode .readable-text-box {
            background: #383838;
            color: #ecf0f1;
            border-color: #555;
        }

        .direct-observation-box {
            padding: 40px 20px; text-align: center; color: #555;
            background: #f0f2f5; border-radius: 12px; border: 2px dashed #ccc;
        }
        .dark-mode .direct-observation-box {
            background: #2d2d2d; color: #aaa; border-color: #555;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header">
            <div class="greeting-box">
                <h2>Evaluation Result</h2>
                <div class="date-box">For: <strong><?php echo htmlspecialchars($data['student_name']); ?></strong></div>
            </div>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <div class="eval-grid">
            
            <div class="context-card">
                <div class="gradient-header gh-blue">
                    <h3 style="margin:0; display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-file-alt"></i> Original Submission
                    </h3>
                </div>
                
                <?php if($data['sub_title']): ?>
                    <div class="modern-input-group">
                        <div class="context-label">Submission Title</div>
                        <div style="font-weight:bold; font-size: 16px;"><?php echo htmlspecialchars($data['sub_title']); ?></div>
                    </div>

                    <div class="modern-input-group">
                        <div class="context-label">Student Description</div>
                        <div class="context-box" style="font-size: 15px; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($data['sub_desc'])); ?>
                        </div>
                    </div>

                    <?php if($data['sub_file']): ?>
                    <a href="<?php echo $data['sub_file']; ?>" target="_blank" class="btn btn-view" style="display:block; text-align:center; padding: 12px; font-weight: bold;">
                        <i class="fas fa-paperclip"></i> View Attached Evidence
                    </a>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="direct-observation-box">
                        <i class="fas fa-eye" style="font-size:40px; color:#3498db; margin-bottom:15px;"></i><br>
                        <strong style="font-size:18px; color:#333;" class="dark-mode-text">Direct Observation</strong><br>
                        <p style="margin-top:10px; font-size:14px; opacity:0.8;">This evaluation was conducted directly by the admin without a specific file submission.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="eval-card">
                <div class="gradient-header gh-green">
                    <h3 style="margin:0; display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-clipboard-check"></i> Assessment Result
                    </h3>
                </div>

                <div class="eval-body">
                    
                    <div class="info-box" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                        <div>
                            <div class="context-label" style="margin-bottom: 5px;">Final Score</div>
                            <div class="score-display">
                                <?php echo $data['competency_score']; ?> <span class="score-total">/ 10</span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="context-label" style="margin-bottom: 5px;">Evaluator</div>
                            <div style="font-weight:bold; font-size: 16px;"><?php echo htmlspecialchars($data['evaluator_name']); ?></div>
                            <div style="font-size:13px; opacity:0.6; margin-top: 5px;">
                                <i class="far fa-calendar-alt"></i> <?php echo date("F d, Y", strtotime($data['upload_date'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="modern-input-group">
                        <div class="context-label">Evaluation Title</div>
                        <div style="font-size:18px; font-weight:bold;"><?php echo htmlspecialchars($data['evaluation_title']); ?></div>
                    </div>

                    <div class="modern-input-group">
                        <div class="context-label" style="margin-bottom: 10px;">Professional Feedback & Notes</div>
                        <div class="readable-text-box">
                            <?php echo nl2br(htmlspecialchars($data['readiness_notes'])); ?>
                        </div>
                    </div>

                    <div class="action-footer" style="text-align:right; margin-top: 25px;">
                        <a href="admin_evaluations.php" class="btn btn-view" style="background:#555; padding: 12px 25px; font-weight: bold;">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <script src="js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>