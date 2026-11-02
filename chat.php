<?php
error_reporting(0);
header("Content-Type: application/json");
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return a specific error code that our JavaScript can understand
    echo json_encode(["error" => "SESSION_EXPIRED"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input['message'] ?? '';
$context = $input['context'] ?? '';

// --- MAGIC SECRETS LOADER ---
// If we are testing locally, look for the hidden .env file
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Grab the key safely from Railway's vault OR the local .env file
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');

if (empty($apiKey)) {
    echo json_encode(["error" => "API Key is missing! Check Railway variables or your .env file."]);
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$prompt = "You are a helpful teaching assistant. Context: \n" . $context . "\n\nUser Question: " . $userMessage;

$data = [
    "contents" => [
        ["parts" => [["text" => $prompt]]]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
// Bypass SSL strictness on some servers
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
curl_close($ch);

$decoded = json_decode($response, true);

if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(["reply" => $decoded['candidates'][0]['content']['parts'][0]['text']]);
} else {
    if(isset($decoded['error']['message'])) {
         echo json_encode(["error" => "Google API Error: " . $decoded['error']['message']]);
    } else {
         echo json_encode(["error" => "Unknown API Error. The key might be invalid."]);
    }
}
?>
