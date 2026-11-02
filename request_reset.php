<?php
session_start();
include 'db_connect.php';

$msg = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    
    $check = $conn->query("SELECT id FROM users WHERE email='$email'");
    if($check->num_rows > 0) {
        $conn->query("UPDATE users SET reset_request=1 WHERE email='$email'");
        $msg = "Request sent! Please contact the Admin to complete the reset.";
    } else {
        $error = "Email address not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Request Password Reset</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #e67e22; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button:hover { background: #d35400; }
        a { color: #3498db; text-decoration: none; font-size: 14px; display: block; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <p style="color:#666; font-size:14px;">Enter your email to request a password reset from the Administrator.</p>
        
        <?php if($msg) echo "<p style='color:green; background:#d4edda; padding:10px;'>$msg</p>"; ?>
        <?php if($error) echo "<p style='color:red; background:#fadbd8; padding:10px;'>$error</p>"; ?>
        
        <form method="POST">
            <input type="email" name="email" placeholder="Enter your email address" required>
            <button type="submit">Send Request</button>
        </form>
        <a href="index.php">Back to Login</a>
    </div>
</body>
</html>