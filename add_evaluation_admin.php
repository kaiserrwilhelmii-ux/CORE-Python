<?php
session_start();
include __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$admin_id = $_SESSION['user_id'];
$students = $conn->query("SELECT * FROM users WHERE role='student_teacher' ORDER BY fullname ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_grade'])) {
    $student_id = intval($_POST['student_id']);
    $eval_title = $_POST['eval_title'];
    $score = $_POST['score'];
    $notes = $_POST['notes'];
    
    $stmt = $conn->prepare("INSERT INTO evaluations (user_id, evaluator_id, submission_id, evaluation_title, competency_score, readiness_notes, status, upload_date) VALUES (?, ?, 0, ?, ?, ?, 'accepted', NOW())");
    $stmt->bind_param("iisss", $student_id, $admin_id, $eval_title, $score, $notes);
    
    if($stmt->execute()) {
        $new_eval_id = $conn->insert_id;
        header("Location: admin_view_evaluation.php?id=$new_eval_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Manual Evaluation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- FIXED SIDEBAR & LAYOUT CSS --- */
        body { display: flex !important; min-height: 100vh; overflow-x: hidden; margin: 0; background: #f4f7f6; }
        .sidebar { width: 250px !important; flex-shrink: 0 !important; position: relative !important; z-index: 1000; min-height: 100vh; }
        .main-content { flex: 1 !important; margin-left: 0 !important; padding: 30px !important; width: calc(100% - 250px) !important; transition: none !important; }
        
        .eval-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start; margin-top: 15px; }
        @media (max-width: 1000px) { .eval-grid { grid-template-columns: 1fr; } }
        
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .ai-header-banner { padding: 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .ai-header-banner h3 { margin: 0 0 5px 0; font-size: 18px; }
        .ai-header-banner p { margin: 0; font-size: 13px; opacity: 0.9; }
        
        .chat-container { display: flex; flex-direction: column; height: 550px; background: #fdfbfb; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; align-items: center; border-top: 1px solid #eee; }
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 85%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; position: relative; }
        .message.user .bubble { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #e9ecef; color: #333; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 11px; margin-bottom: 4px; opacity: 0.6; }
        .typing-indicator { display: none; padding: 10px 20px; font-style: italic; color: #888; font-size: 12px; background:#fff;}
        
        .modern-input-group { margin-bottom: 15px; padding: 0 20px; }
        .modern-label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #555; }
        .modern-input, .modern-textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; }
        .btn-submit-premium { background: #27ae60; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; }
        .btn-ai-glow { background: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 20px; cursor: pointer; }
        .btn-ai-glow:disabled { background: #ccc; cursor: not-allowed; }

        #aiLoadingOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 99999; color: white; flex-direction: column; justify-content: center; align-items: center; font-family: sans-serif; }
        .ai-spinner { border: 6px solid #f3f3f3; border-top: 6px solid #27ae60; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header" style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <div class="greeting-box">
                <h2 style="margin:0;">Direct Observation</h2>
                <div class="date-box" style="opacity:0.7; font-size:14px;">Create Manual Evaluation</div>
            </div>
            <button id="themeToggle" class="theme-toggle" style="background:#ecf0f1; border:none; padding:8px 15px; border-radius:20px; cursor:pointer;"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <div class="eval-grid">
            
            <form method="POST">
                <div class="card">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); margin-bottom: 20px;">
                        <div class="ai-header-content">
                            <h3><i class="fas fa-edit"></i> Evaluation Details</h3>
                            <p>Enter your observation notes below.</p>
                        </div>
                    </div>

                    <div class="eval-body">
                        <div class="modern-input-group">
                            <label class="modern-label">Select Student</label>
                            <select name="student_id" id="studentSelect" class="modern-input" required>
                                <option value="">-- Choose a Student --</option>
                                <?php while($s = $students->fetch_assoc()): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['fullname']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="modern-input-group">
                            <label class="modern-label">Evaluation Title</label>
                            <input type="text" name="eval_title" id="evalTitle" class="modern-input" value="Direct Observation: <?php echo date("M d, Y"); ?>" required>
                        </div>

                        <div class="modern-input-group">
                            <label class="modern-label">Competency Score (1-10)</label>
                            <input type="number" name="score" class="modern-input" min="1" max="10" required>
                        </div>

                        <div class="modern-input-group">
                            <label class="modern-label">Professional Feedback & Notes</label>
                            <textarea name="notes" id="evalNotes" class="modern-textarea" rows="12" placeholder="Type your observation here... (The AI can help you refine this!)" required></textarea>
                        </div>

                        <div class="modern-input-group" style="margin-top:20px; padding-bottom: 20px;">
                            <button type="submit" name="submit_grade" class="btn-submit-premium">
                                <i class="fas fa-save"></i> Save Evaluation
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card" style="border:2px solid #3498db;">
                <div class="ai-header-banner" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                    <div class="ai-header-content">
                        <h3><i class="fas fa-robot"></i> Copilot Assistant</h3>
                        <p>I can help you phrase feedback.</p>
                    </div>
                </div>
                
                <div class="chat-container">
                    <div class="chat-history" id="chatHistory">
                        <div class="message ai">
                            <div class="sender-name">Copilot</div>
                            <div class="bubble">Hello! Start typing your observation notes on the left, and ask me to "Refine this" or "Check against Domain 1".</div>
                        </div>
                    </div>
                    
                    <div class="typing-indicator" id="typingIndicator"><i class="fas fa-circle-notch fa-spin"></i> Copilot is thinking...</div>

                    <form id="chatForm" onsubmit="event.preventDefault(); sendMessage();" style="margin: 0;">
                        <div class="chat-input-area">
                            <input type="text" id="chatInput" class="modern-input" placeholder="Ask Copilot..." required style="margin-bottom:0; border-radius:20px;">
                            <button type="submit" id="sendChatBtn" class="btn-ai-glow" style="border-radius:50%;"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <div id="aiLoadingOverlay">
        <div class="ai-spinner"></div>
        <h2>Saving Evaluation...</h2>
    </div>

    <script src="js/script.js?v=<?php echo time(); ?>"></script>
    <script>
        const chatHistory = document.getElementById('chatHistory');
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('sendChatBtn');
        const typingIndicator = document.getElementById('typingIndicator');
        const studentSelect = document.getElementById('studentSelect');
        const evalTitle = document.getElementById('evalTitle');
        const evalNotes = document.getElementById('evalNotes');

        function sendMessage() {
            const message = chatInput.value.trim();
            if (message === "") return;

            chatInput.disabled = true;
            sendBtn.disabled = true;

            addMessageToUI('You', message, 'user');
            chatInput.value = '';
            typingIndicator.style.display = 'block';

            let studentName = studentSelect.selectedIndex > 0 ? studentSelect.options[studentSelect.selectedIndex].text : "Unknown Student";
            let contextData = `TASK: Admin Manual Eval. STUDENT: ${studentName}. TITLE: ${evalTitle.value}. NOTES: "${evalNotes.value}"`;

            const payload = {
                message: message,
                context: contextData,
                mode: 'consultant'
            };

            fetch('api_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload) 
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if(data.error) { addMessageToUI('System Error', data.error, 'ai'); } 
                    else if(data.reply) { addMessageToUI('Copilot', data.reply, 'ai'); }
                } catch (e) {
                    addMessageToUI('System Error', 'Server blocked the request or session expired.', 'ai');
                }
            })
            .catch(error => { addMessageToUI('System Error', 'Connection Error.', 'ai'); })
            .finally(() => {
                typingIndicator.style.display = 'none';
                chatInput.disabled = false;
                sendBtn.disabled = false;
                chatInput.focus();
            });
        }

        function addMessageToUI(sender, text, type) {
            const msgDiv = document.createElement('div');
            msgDiv.classList.add('message', type);
            msgDiv.innerHTML = `<div class="sender-name">${sender}</div><div class="bubble">${text.replace(/\n/g, "<br>")}</div>`;
            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }

        document.addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'submit_grade') {
                document.getElementById('aiLoadingOverlay').style.display = 'flex';
            }
        });
    </script>
</body>
</html>
