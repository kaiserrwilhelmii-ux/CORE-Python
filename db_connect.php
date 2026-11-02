<?php
// Fallbacks included to prevent Railway crashes
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE') ?: 'railway'; 
$port = getenv('MYSQLPORT') ?: 3306;

// Connect AND select the database in one step
$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>