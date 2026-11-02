<?php
session_start();
// Removed the JSON header because we are now doing a normal page redirect

ini_set('display_errors', 0);
error_reporting(E_ALL);

include __DIR__ . '/db_connect.php';
include __DIR__ . '/ai_helper.php'; 

// 1. TEXT EXTRACTION FUNCTION (Native PHP for DOCX/TXT)
function extractTextFromFile($filePath) {
    if (!file_exists($filePath)) {
        return "[System Error: File not found at path: $filePath]";
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $text = "";

    if ($ext === 'docx') {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === TRUE) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $xml = $zip->getFromIndex($index);
                    $dom = new DOMDocument;
                    $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                    $text = strip_tags($dom->saveXML());
                } else {
                    $text = "[System Note: DOCX structure valid, but no text content found.]";
                }
                $zip->close();
            } else {
                $text = "[System Note: Could not open DOCX. File may be corrupted.]";
            }
        } else {
            $text = "[System Note: Server missing ZipArchive capability.]";
        }
    } elseif ($ext === 'txt') {
        $text = file_get_contents($filePath);
    } 

    return substr(trim($text), 0, 15000); 
}

// 2. MAIN LOGIC
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

try {
    // Using standard $_POST since we are using a real HTML form now
    $message = $_POST['message'] ?? '';
    $context = $_POST['context'] ?? '';
    $mode    = $_POST['mode'] ?? 'mentor';
    $student_file_path = $_POST['student_file_path'] ?? '';

    $file_text = ""; 
    $pdf_base64 = null; 

    // Check Chat Upload
    if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['chat_file']['name'], PATHINFO_EXTENSION));
        
        if ($ext === 'pdf') {
            $pdf_base64 = base64_encode(file_get_contents($_FILES['chat_file']['tmp_name']));
            $file_text .= "\n\n[SYSTEM: A PDF file has been natively attached for you to analyze.]\n";
        } else {
            $extracted = extractTextFromFile($_FILES['chat_file']['tmp_name']);
            if (!empty($extracted)) {
                $file_text .= "\n\n=== NEW ATTACHMENT ===\n" . $extracted . "\n======================\n";
            }
        }
    }

    // Check Portfolio File
    if (!empty($student_file_path) && $pdf_base64 === null) {
        $clean_path = ltrim(str_replace(['../', '..\\'], '', $student_file_path), '/\\');
        $full_path = __DIR__ . '/' . $clean_path; 
        
        if (file_exists($full_path)) {
            $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
            
            if ($ext === 'pdf') {
                $pdf_base64 = base64_encode(file_get_contents($full_path));
                $file_text .= "\n\n[SYSTEM: The student's PDF portfolio file is attached natively.]\n";
            } else {
                $extracted_student_file = extractTextFromFile($full_path);
                if (!empty($extracted_student_file)) {
                    $file_text .= "\n\n=== STUDENT SUBMISSION FILE CONTENT ===\n" . $extracted_student_file . "\n=======================================\n";
                }
            }
        }
    }

    // Generate Response
    if (!empty($message) && $user_id > 0) {
        
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $user_id, $message);
        $stmt->execute();
        
        $full_prompt = "CONTEXT:\n$context\n$file_text\n\nUSER QUESTION:\n$message";
        
        $ai_response = generateAIResponse($full_prompt, $mode, $pdf_base64);

        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
        $stmt->bind_param("is", $user_id, $ai_response);
        $stmt->execute();
    }

} catch (Throwable $e) { // Changed to Throwable to catch severe fatal errors too
    if ($user_id > 0) {
        // If there's a PHP error, tell the AI Copilot to print it in the chat box!
        $error_msg = "System Error: " . $e->getMessage();
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
        $stmt->bind_param("is", $user_id, $error_msg);
        $stmt->execute();
    }
}

// 3. THE MAGIC REDIRECT
// This sends the browser seamlessly back to the workspace after the AI answers
header("Location: student_portfolio.php");
exit();
?>