<?php
// Suppress warnings for a clean user experience
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
session_start();
include 'db_connect.php';

$msg = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic Info
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Auto-assigned System Data
    $role     = 'student_teacher'; // Automatically force this role
    $status   = 'active'; 

    // New Academic & Personal Info
    $college        = trim($_POST['college'] ?? '');
    $course         = trim($_POST['course'] ?? '');
    $year_level     = trim($_POST['year_level'] ?? '');
    $section        = trim($_POST['section'] ?? '');
    $birthdate      = $_POST['birthdate'] ?? null;
    $gender         = $_POST['gender'] ?? '';
    $address        = trim($_POST['address'] ?? '');
    $partner_school = trim($_POST['partner_school'] ?? '');

    // Auto-calculate Age based on Birthdate
    $age = 0;
    if (!empty($birthdate)) {
        $dob = new DateTime($birthdate);
        $now = new DateTime('today');
        $age = $dob->diff($now)->y;
    }

    // Check if username or email already exists to prevent duplicates
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check_stmt->bind_param("ss", $email, $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error = "An account with that username or email already exists.";
    } else {
        // Insert new user with all fields
        $sql = "INSERT INTO users (username, fullname, email, password, role, status, college, course, year_level, section, birthdate, age, gender, address, partner_school) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        // Types: 14 strings (s), 1 integer (i) for age
        $stmt->bind_param("sssssssssssisss", $username, $fullname, $email, $password, $role, $status, $college, $course, $year_level, $section, $birthdate, $age, $gender, $address, $partner_school);
        
        if ($stmt->execute()) {
            $msg = "Account created successfully! You can now log in.";
        } else {
            $error = "Error creating account: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Competency and Readiness Evaluation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            display: flex; justify-content: center; align-items: center; 
            min-height: 100vh; background-color: #f4f7f6; margin: 0; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            padding: 20px 0;
        }
        .register-box { 
            background: #fff; padding: 40px; border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; 
            max-width: 700px; /* Widened to fit the grid cleanly */
        }
        .register-box h2 { margin-top: 0; color: #333; margin-bottom: 25px; text-align: center; }
        
        /* 2-Column Grid Layout for the Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: span 2; }
        
        label { font-size: 13px; font-weight: bold; color: #555; margin-bottom: 5px; }
        
        .form-control { 
            width: 100%; padding: 10px; border: 1px solid #ccc; 
            border-radius: 4px; box-sizing: border-box; 
            background: #fff; color: #333; font-size: 14px;
        }
        
        .btn-submit { 
            width: 100%; padding: 12px; background: #2980b9; color: white; 
            border: none; border-radius: 4px; cursor: pointer; 
            font-size: 16px; font-weight: bold; transition: background 0.3s; 
            margin-top: 10px;
        }
        .btn-submit:hover { background: #2471a3; }
        .auth-link { 
            display: block; margin-top: 20px; color: #2980b9; 
            text-decoration: none; font-size: 14px; text-align: center;
        }
        .auth-link:hover { text-decoration: underline; }
        
        /* Responsive adjustments for mobile phones */
        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="register-box">
        <h2>Account Registration</h2>
        
        <?php if($error) echo "<p style='color:#e74c3c; background:#fdedec; padding:10px; border-radius:4px; font-size:14px;'><i class='fas fa-exclamation-circle'></i> $error</p>"; ?>
        <?php if($msg) echo "<p style='color:#27ae60; background:#eafaf1; padding:10px; border-radius:4px; font-size:14px;'><i class='fas fa-check-circle'></i> $msg</p>"; ?>
        
        <form method="POST">
            <div class="form-grid">
                
                <div class="form-group full-width" style="border-bottom: 1px solid #eee; padding-bottom:10px; margin-bottom: 5px;">
                    <h4 style="margin:0; color:#2980b9;">Account Credentials</h4>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Choose a Username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Create a Password" required>
                </div>

                <div class="form-group full-width" style="border-bottom: 1px solid #eee; padding-bottom:10px; margin-bottom: 5px; margin-top: 10px;">
                    <h4 style="margin:0; color:#2980b9;">Personal Information</h4>
                </div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" class="form-control" placeholder="First Last" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
                </div>
                <div class="form-group">
                    <label>Birthdate</label>
                    <input type="date" name="birthdate" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control" required>
                        <option value="" disabled selected>Select...</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Complete Address</label>
                    <input type="text" name="address" class="form-control" placeholder="House No., Street, City, Province" required>
                </div>

                <div class="form-group full-width" style="border-bottom: 1px solid #eee; padding-bottom:10px; margin-bottom: 5px; margin-top: 10px;">
                    <h4 style="margin:0; color:#2980b9;">Academic Details</h4>
                </div>
                
                <div class="form-group">
                    <label>College</label>
                    <input type="text" name="college" class="form-control" placeholder="e.g. College of Education" required>
                </div>
                <div class="form-group">
                    <label>Course</label>
                    <input type="text" name="course" class="form-control" placeholder="e.g. BSEd Major in English" required>
                </div>
                <div class="form-group">
                    <label>Year Level</label>
                    <input type="text" name="year_level" class="form-control" placeholder="e.g. 4th Year" required>
                </div>
                <div class="form-group">
                    <label>Section</label>
                    <input type="text" name="section" class="form-control" placeholder="e.g. A" required>
                </div>
                <div class="form-group full-width">
                    <label>Partner School (OJT Deployment)</label>
                    <input type="text" name="partner_school" class="form-control" placeholder="Name of deployment school" required>
                </div>
                
            </div>

            <button type="submit" class="btn-submit">Register Account</button>
            <a href="index.php" class="auth-link">Already have an account? Login here</a>
        </form>
    </div>
</body>
</html>
