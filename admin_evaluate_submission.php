<?php
session_start();
include __DIR__ . '/db_connect.php';
include __DIR__ . '/ai_helper.php';

function extractTextForEvaluation($filePath) {
    if (!file_exists($filePath)) { return "[SYSTEM ALERT: File missing. Evaluate based on Description only.]"; }
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $text = "";
    
    if ($ext === 'pdf') { return "[SYSTEM ALERT: PDF uploaded. AI cannot read PDFs. Evaluate based on Description only.]"; }

    if ($ext === 'docx') {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === TRUE) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $xml = $zip->getFromIndex($index);
                    $dom = new DOMDocument;
                    @$dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                    $text = strip_tags($dom->saveXML());
                }
                $zip->close();
            }
        } else {
            // RAILWAY CLOUD BYPASS: Use Linux shell to unzip if PHP extension is disabled
            $xml = @shell_exec("unzip -p " . escapeshellarg($filePath) . " word/document.xml 2>/dev/null");
            if ($xml) {
                $dom = new DOMDocument;
                @$dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $text = strip_tags($dom->saveXML());
            } else {
                return "[SYSTEM ALERT: Cannot extract DOCX on this server. Evaluate based on Description only.]";
            }
        }
    } elseif ($ext === 'txt') {
        $text = file_get_contents($filePath);
    } else {
        return "[SYSTEM NOTE: File type .$ext not supported.]";
    }

    $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text); 
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    return substr(trim($text), 0, 15000); 
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }
$admin_id = $_SESSION['user_id'];
$sub_id = isset($_GET['sub_id']) ? intval($_GET['sub_id']) : 0;

$sub_q = $conn->query("SELECT s.*, u.fullname FROM submissions s JOIN users u ON s.user_id = u.id WHERE s.id=$sub_id");
if($sub_q->num_rows == 0) { header("Location: admin_dashboard.php"); exit(); }
$sub = $sub_q->fetch_assoc();

$extracted_file_text = "";
$file_content_msg = "";
$actual_path = __DIR__ . '/' . ltrim(str_replace(['../', '..\\'], '', $sub['file_path']), '/\\');

if (!empty($sub['file_path']) && file_exists($actual_path)) {
    $extracted_file_text = extractTextForEvaluation($actual_path);
    $file_content_msg = "\n\n=== [EVIDENCE FILE CONTENT] ===\n" . $extracted_file_text . "\n==============================\n";
}

$master_context = "Title: " . $sub['title'] . "\nStudent Context Description: " . $sub['description'] . $file_content_msg;
$ai_scores = ['obj' => '', 'con' => '', 'meth' => '', 'ass' => '', 'fmt' => '', 'total' => ''];
$ai_feedback = "";

// --- CHAT LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_chat'])) {
    $message = trim($_POST['message'] ?? '');
    $context = $_POST['context'] ?? '';
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $admin_id, $message); $stmt->execute();
        $ai_response = generateAIResponse("Context:\n$context\n\nUser Question: $message", 'consultant');
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
        $stmt->bind_param("is", $admin_id, $ai_response); $stmt->execute();
        header("Location: " . $_SERVER['REQUEST_URI']); exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['generate_ai'])) {
        $full_prompt = "You are a professional teacher evaluator grading a lesson plan. Use this exact 100-point rubric:
        1. Objectives (Max 20)
        2. Content (Max 20)
        3. Methodology (Max 30)
        4. Assessment (Max 20)
        5. Formatting (Max 10)
        
        Student Work: " . $master_context . "
        
        CRITICAL: If the document content contains a SYSTEM ALERT about a missing or unreadable file, DO NOT PANIC AND DO NOT FAIL. You MUST evaluate the lesson plan based SOLELY on the 'Student Context Description'.
        
        RETURN ONLY VALID JSON EXACTLY LIKE THIS FORMAT. DO NOT ADD ANY OTHER TEXT:
        {
            \"obj\": 18,
            \"con\": 15,
            \"meth\": 25,
            \"ass\": 15,
            \"fmt\": 10,
            \"total\": 83,
            \"feedback\": \"Detailed feedback...\"
        }";
        
        $raw_ai_text = generateAIResponse($full_prompt, 'evaluator');
        if (preg_match('/\{[\s\S]*\}/', $raw_ai_text, $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed && isset($parsed['total'])) {
                $ai_scores = $parsed; $ai_feedback = $parsed['feedback'];
            } else { $ai_feedback = "AI JSON Error. Raw output: " . $matches[0]; }
        } else { $ai_feedback = "AI failed to return the scoring format. Raw output: " . $raw_ai_text; }

    } elseif (isset($_POST['submit_grade'])) {
        $eval_title = $_POST['eval_title']; $score = $_POST['human_total']; 
        $notes = "RUBRIC BREAKDOWN:\nObjectives: " . $_POST['human_obj'] . "/20\nContent: " . $_POST['human_con'] . "/20\nMethodology: " . $_POST['human_meth'] . "/30\nAssessment: " . $_POST['human_ass'] . "/20\nFormatting: " . $_POST['human_fmt'] . "/10\n\nFEEDBACK:\n" . $_POST['notes']; 
        
        $student_id = $sub['user_id'];
        $target_file = $sub['file_path'];
        if (!empty($_FILES['file']['name'])) {
            $target_dir = "uploads/";
            $target_file = $target_dir . "admin_" . time() . "_" . basename($_FILES["file"]["name"]);
            move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
        }
        $stmt = $conn->prepare("INSERT INTO evaluations (user_id, evaluator_id, submission_id, evaluation_title, competency_score, readiness_notes, file_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiisiss", $student_id, $admin_id, $sub_id, $eval_title, $score, $notes, $target_file);
        if($stmt->execute()) {
            $conn->query("UPDATE submissions SET status='evaluated' WHERE id=$sub_id");
            header("Location: admin_evaluations.php?student_id=$student_id"); exit();
        }
    }
}
$chat_history = $conn->query("SELECT * FROM chat_logs WHERE user_id=$admin_id ORDER BY created_at ASC");
$file_url = htmlspecialchars($sub['file_path']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Administrator Evaluation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { display: flex; height: 100vh; overflow: hidden; margin: 0; background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; box-sizing: border-box; }
        .eval-grid { display: flex; flex-wrap: wrap; gap: 25px; margin-top: 20px; align-items: flex-start; }
        .column-left { flex: 1; min-width: 500px; display: flex; flex-direction: column; gap: 20px; }
        .column-right { width: 380px; flex-shrink: 0; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; box-sizing: border-box; }
        .eval-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .ai-header-banner { padding: 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        
        .dual-rubric { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .rubric-side { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; }
        .rubric-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 13px; }
        .rubric-input { width: 60px; padding: 6px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-weight: bold; }
        .ai-input { background: #eef2f5; color: #7f8c8d; border-color: #d1d8e0; }
        .rubric-total { font-size: 18px; font-weight: bold; text-align: right; padding-top: 10px; border-top: 2px solid #ddd; margin-top: 10px; }
        
        .chat-container { display: flex; flex-direction: column; height: 600px; background: #fdfbfb; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; align-items: center; border-top: 1px solid #eee; }
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 85%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; word-wrap: break-word; }
        .message.user .bubble { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #e9ecef; color: #333; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 11px; margin-bottom: 4px; opacity: 0.6; }
        .modern-input-group { margin-bottom: 15px; }
        .modern-label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #555; }
        .modern-textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; }
        
        .action-container { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; border-top: 2px dashed #eee; padding-top: 20px; }
        
        /* NEW BUTTON STYLES FOR LOCKOUT LOGIC */
        .btn-run-ai { background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%); color: white; border: none; padding: 15px; border-radius: 6px; font-weight: bold; font-size: 15px; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(142, 68, 173, 0.3); transition: all 0.3s; }
        .btn-run-ai:not(:disabled):hover { transform: translateY(-2px); cursor: pointer; }
        .btn-run-ai:disabled { background: #bdc3c7; box-shadow: none; cursor: not-allowed; opacity: 0.7; }
        
        .btn-submit-official { background: #27ae60; color: white; border: none; padding: 15px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3); transition: background 0.2s; }
        .btn-submit-official:hover { background: #2ecc71; }
        .btn-ai-glow { background: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 50%; cursor: pointer; }
        #aiLoadingOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 99999; color: white; flex-direction: column; justify-content: center; align-items: center; }
        .ai-spinner { border: 6px solid #f3f3f3; border-top: 6px solid #3498db; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header" style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <div class="greeting-box">
                <h2 style="margin:0;">Administrator Evaluation</h2>
                <div class="date-box" style="opacity:0.7; font-size:14px;"><?php echo htmlspecialchars($sub['fullname']); ?></div>
            </div>
            <button id="themeToggle" class="theme-toggle" style="background:#ecf0f1; border:none; padding:8px 15px; border-radius:20px; cursor:pointer;"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <div class="eval-grid">
            <div class="column-left">
                <div class="card">
                    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;"><i class="fas fa-user-graduate"></i> Submission Context</h3>
                    <div style="font-weight:bold; font-size:16px; margin-bottom:10px;"><?php echo htmlspecialchars($sub['title']); ?></div>
                    <div style="background:#f9f9f9; padding:10px; border-radius:4px; font-size:14px; margin-bottom:15px;"><?php echo nl2br(htmlspecialchars($sub['description'])); ?></div>
                    <?php if (!empty($sub['file_path'])): ?>
                        <div style="margin-top: 15px; width: 100%;">
                            <a href="<?php echo $file_url; ?>" download class="btn-submit-premium" style="display:block; text-align:center; text-decoration:none; background:#2c3e50; padding:12px; box-sizing: border-box; width: 100%; border-radius: 4px; color: white;">
                                <i class="fas fa-download"></i> Download Evidence File
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="padding:15px; background:#f8f9fa; text-align:center; border-radius:6px; border:1px dashed #ccc; color:#6c757d;">No file attached.</div>
                    <?php endif; ?>
                </div>

                <form method="POST" enctype="multipart/form-data" id="mainForm">
                    <div class="eval-card">
                        <div class="ai-header-banner" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);">
                            <div class="ai-header-content"><h3 style="margin:0;"><i class="fas fa-tasks"></i> 100-Point Evaluation</h3></div>
                        </div>
                        <div class="eval-body" style="padding:25px;">
                            <div class="modern-input-group">
                                <label class="modern-label">Evaluation Title</label>
                                <input type="text" name="eval_title" class="modern-textarea" value="Admin Official Evaluation" required>
                            </div>
                            
                            <div class="dual-rubric">
                                <div class="rubric-side">
                                    <h4 style="margin-top:0; color:#2c3e50; border-bottom:1px solid #ccc; padding-bottom:5px;">AI Suggested Score</h4>
                                    <div class="rubric-row"><span>1. Objectives (20)</span> <input type="text" class="rubric-input ai-input" id="ai_obj" value="<?= $ai_scores['obj'] ?>" readonly></div>
                                    <div class="rubric-row"><span>2. Content (20)</span> <input type="text" class="rubric-input ai-input" id="ai_con" value="<?= $ai_scores['con'] ?>" readonly></div>
                                    <div class="rubric-row"><span>3. Methodology (30)</span> <input type="text" class="rubric-input ai-input" id="ai_meth" value="<?= $ai_scores['meth'] ?>" readonly></div>
                                    <div class="rubric-row"><span>4. Assessment (20)</span> <input type="text" class="rubric-input ai-input" id="ai_ass" value="<?= $ai_scores['ass'] ?>" readonly></div>
                                    <div class="rubric-row"><span>5. Formatting (10)</span> <input type="text" class="rubric-input ai-input" id="ai_fmt" value="<?= $ai_scores['fmt'] ?>" readonly></div>
                                    <div class="rubric-total" style="color:#2980b9;">AI Total: <span id="ai_total"><?= empty($ai_scores['total']) ? '0' : $ai_scores['total'] ?></span>/100</div>
                                    <button type="button" onclick="copyAIScores()" style="width:100%; padding:5px; margin-top:10px; background:#bdc3c7; border:none; border-radius:4px; cursor:pointer;"><i class="fas fa-arrow-right"></i> Copy AI Scores</button>
                                </div>

                                <div class="rubric-side" style="border-color:#3498db; background:#f0f8ff;">
                                    <h4 style="margin-top:0; color:#2980b9; border-bottom:1px solid #3498db; padding-bottom:5px;">Step 1: Official Grade</h4>
                                    <div class="rubric-row"><span>1. Objectives (20)</span> <input type="number" name="human_obj" class="rubric-input human-calc" id="h_obj" max="20" required></div>
                                    <div class="rubric-row"><span>2. Content (20)</span> <input type="number" name="human_con" class="rubric-input human-calc" id="h_con" max="20" required></div>
                                    <div class="rubric-row"><span>3. Methodology (30)</span> <input type="number" name="human_meth" class="rubric-input human-calc" id="h_meth" max="30" required></div>
                                    <div class="rubric-row"><span>4. Assessment (20)</span> <input type="number" name="human_ass" class="rubric-input human-calc" id="h_ass" max="20" required></div>
                                    <div class="rubric-row"><span>5. Formatting (10)</span> <input type="number" name="human_fmt" class="rubric-input human-calc" id="h_fmt" max="10" required></div>
                                    <input type="hidden" name="human_total" id="h_total_input">
                                    <div class="rubric-total" style="color:#27ae60;">Official Total: <span id="h_total_display">0</span>/100</div>
                                </div>
                            </div>

                            <div class="modern-input-group">
                                <label class="modern-label">Detailed Feedback</label>
                                <textarea name="notes" id="feedback_box" class="modern-textarea" rows="8"><?php echo htmlspecialchars($ai_feedback); ?></textarea>
                            </div>
                            
                            <div class="action-container">
                                <button type="submit" name="generate_ai" id="runAiBtn" class="btn-run-ai" disabled title="Please fill all 5 official grade boxes first.">
                                    <i class="fas fa-magic"></i> Step 2: Run AI Analyzer (Fill Rubric First)
                                </button>
                                <button type="submit" name="submit_grade" class="btn-submit-official">
                                    <i class="fas fa-check-circle"></i> Step 3: Submit Final Grade & Finish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="column-right">
                <div class="eval-card" style="border:2px solid #3498db;">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                        <div class="ai-header-content"><h3 style="margin:0;"><i class="fas fa-robot"></i> Consultant Chat</h3></div>
                    </div>
                    <div class="chat-container">
                        <div class="chat-history" id="chatHistory">
                            <?php if ($chat_history->num_rows > 0): while($chat = $chat_history->fetch_assoc()): ?>
                                <div class="message <?php echo $chat['sender']; ?>">
                                    <div class="sender-name"><?php echo ($chat['sender'] == 'user') ? 'You' : 'Consultant'; ?></div>
                                    <div class="bubble"><?php echo nl2br(htmlspecialchars($chat['message'])); ?></div>
                                </div>
                            <?php endwhile; else: ?>
                                <div class="message ai"><div class="sender-name">Consultant</div><div class="bubble">Hello Administrator. I can read the student's context description and attached files. How can I help?</div></div>
                            <?php endif; ?>
                        </div>
                        <form method="POST" id="chatForm" style="margin: 0; padding: 0;">
                            <div class="chat-input-area">
                                <input type="hidden" name="context" value="<?php echo htmlspecialchars($master_context); ?>">
                                <input type="text" name="message" class="modern-textarea" placeholder="Ask a question..." required style="margin-bottom:0; border-radius:20px; padding:10px;">
                                <button type="submit" name="send_chat" class="btn-ai-glow" style="background:#3498db;"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="aiLoadingOverlay">
        <div class="ai-spinner"></div>
        <h2 id="loadingText">Processing...</h2>
    </div>

    <script>
        const humanInputs = document.querySelectorAll('.human-calc');
        const hTotalDisplay = document.getElementById('h_total_display');
        const hTotalInput = document.getElementById('h_total_input');
        const aiBtn = document.getElementById('runAiBtn');

        function checkHumanInputsAndTotal() {
            let total = 0;
            let allFilled = true;
            humanInputs.forEach(input => { 
                if (input.value === "") { allFilled = false; }
                total += Number(input.value) || 0; 
            });
            hTotalDisplay.innerText = total;
            hTotalInput.value = total;
            
            // LOCKOUT LOGIC: Disable AI button until human inputs are filled
            if(allFilled) {
                aiBtn.disabled = false;
                aiBtn.innerHTML = '<i class="fas fa-magic"></i> Step 2: Run AI Analyzer';
            } else {
                aiBtn.disabled = true;
                aiBtn.innerHTML = '<i class="fas fa-lock"></i> Step 2: Run AI Analyzer (Fill Rubric First)';
            }
        }

        humanInputs.forEach(input => { input.addEventListener('input', checkHumanInputsAndTotal); });
        checkHumanInputsAndTotal(); // Run on load

        function copyAIScores() {
            document.getElementById('h_obj').value = document.getElementById('ai_obj').value || 0;
            document.getElementById('h_con').value = document.getElementById('ai_con').value || 0;
            document.getElementById('h_meth').value = document.getElementById('ai_meth').value || 0;
            document.getElementById('h_ass').value = document.getElementById('ai_ass').value || 0;
            document.getElementById('h_fmt').value = document.getElementById('ai_fmt').value || 0;
            checkHumanInputsAndTotal();
        }

        const chatHistory = document.getElementById('chatHistory');
        if(chatHistory) { chatHistory.scrollTop = chatHistory.scrollHeight; }

        document.addEventListener('submit', function(e) {
            if (e.target.id === 'mainForm') {
                if (e.submitter && e.submitter.name === 'generate_ai') {
                    document.getElementById('loadingText').innerText = 'AI is Calculating 100-Point Rubric...';
                } else {
                    document.getElementById('loadingText').innerText = 'Saving Evaluation...';
                }
            } else if (e.target.id === 'chatForm') {
                document.getElementById('loadingText').innerText = 'Consulting AI...';
            }
            document.getElementById('aiLoadingOverlay').style.display = 'flex';
        });
    </script>
</body>
</html>
