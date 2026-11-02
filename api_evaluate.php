<?php
header('Content-Type: application/json');
session_start();
include 'db_connect.php';

$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$data = json_decode(file_get_contents("php://input"), true);
$submission_id = $data['submission_id'] ?? 0;

if (!$submission_id) {
    echo json_encode(['error' => 'No submission ID provided']);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM submissions WHERE id = ?");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

if (!$submission) {
    echo json_encode(['error' => 'Submission not found']);
    exit();
}

$file_path = $submission['file_path'];
$title = $submission['title'];
$context = $submission['description'];
$file_content = "";

if (file_exists($file_path)) {
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if ($ext === 'docx') {
        // Special function to read Word Documents
        $file_content = read_docx($file_path);
    } elseif (in_array($ext, ['txt', 'php', 'html', 'css', 'js', 'json', 'md', 'sql', 'py', 'c'])) {
        // Read standard text/code files
        $file_content = file_get_contents($file_path);
    } else {
        $file_content = "[System Note: The file is a binary format ($ext). Please evaluate based on the Title and Context provided, as I cannot read this file type directly.]";
    }
} else {
    $file_content = "[System Note: File not found on server.]";
}

$prompt = "You are a strict academic supervisor evaluating a student submission.\n";
$prompt .= "DETAILS:\n";
$prompt .= "Title: $title\n";
$prompt .= "Student Context: $context\n\n";
$prompt .= "FILE CONTENT:\n" . substr($file_content, 0, 20000) . "\n\n";
$prompt .= "TASK: Provide a structured evaluation in JSON format with these fields: 'score' (1-10), 'title' (a short summary title), and 'feedback' (detailed critique).";

$postData = [
    "contents" => [
        ["parts" => [["text" => $prompt]]]
    ],
    "generationConfig" => [
        "responseMimeType" => "application/json"
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

$response = curl_exec($ch);
curl_close($ch);

echo $response;

function read_docx($filename) {
    $striped_content = '';
    $content = '';

    if(!$filename || !file_exists($filename)) return '';

    $zip = zip_open($filename);
    if (!$zip || is_numeric($zip)) return '';

    while ($zip_entry = zip_read($zip)) {
        if (zip_entry_open($zip, $zip_entry) == FALSE) continue;

        if (zip_entry_name($zip_entry) != "word/document.xml") continue;

        $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
        zip_entry_close($zip_entry);
    }
    zip_close($zip);

    $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
    $content = str_replace('</w:r></w:p>', "\r\n", $content);
    $striped_content = strip_tags($content);

    return $striped_content;
}
?>
