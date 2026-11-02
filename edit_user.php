<?php
// Force errors to show during debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: index.php"); 
    exit(); 
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = ""; 
$error = "";

$user_q = $conn->query("SELECT * FROM users WHERE id=$id");
if ($user_q->num_rows == 0) { 
    header("Location: admin_manage.php"); 
    exit(); 
}
$user = $user_q->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $gender   = $_POST['gender'];
    
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL;
    
    $college        = trim($_POST['college']);
    $course         = trim($_POST['course']);
    $year_level     = trim($_POST['year_level']);
    $section        = trim($_POST['section']);
    $address        = trim($_POST['address']);
    $partner_school = trim($_POST['partner_school']);
    $role           = $_POST['role'];
    $status         = $_POST['status'];
    $supervisor_id  = intval($_POST['supervisor_id'] ?? 0);

    $age = 0;
    if (!empty($birthdate)) {
        try {
            $dob = new DateTime($birthdate);
            $now = new DateTime('today');
            $age = $dob->diff($now)->y;
        } catch(Exception $e) { 
            $age = 0; 
        }
    }

    $new_pass = $_POST['new_password'] ?? '';
    $sql = "UPDATE users SET username=?, fullname=?, email=?, age=?, gender=?, birthdate=?, college=?, course=?, year_level=?, section=?, address=?, partner_school=?, assigned_supervisor_id=?, role=?, status=?";
    $types = "ssssssssssssiss";
    $params = [$username, $fullname, $email, $age, $gender, $birthdate, $college, $course, $year_level, $section, $address, $partner_school, $supervisor_id, $role, $status];

    if (!empty($new_pass)) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $sql .= ", password=?, reset_request=0"; 
        $types .= "s"; 
        $params[] = $hashed;
    }
    $sql .= " WHERE id=?"; 
    $types .= "i"; 
    $params[] = $id;

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $msg = "User record successfully updated!";
            $user = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
        } else { 
            $error = "Database Error: " . $conn->error; 
        }
    } catch (mysqli_sql_exception $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

$supervisors = $conn->query("SELECT id, fullname FROM users WHERE role='supervisor' OR role='admin'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | <?= htmlspecialchars($user['fullname'] ?? 'Record') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Workday-Style Modular Card */
        .workday-wrapper {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            min-height: 600px;
        }

        /* Inner Left Navigation (Workday Sidebar) */
        .workday-nav {
            background-color: rgba(0, 0, 0, 0.02);
            border-right: 1px solid var(--border-color);
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        body.dark-mode .workday-nav {
            background-color: rgba(255, 255, 255, 0.02);
        }

        .workday-nav-header {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            color: #888;
            padding: 10px 14px 15px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 10px;
        }

        .section-tab-btn {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border: none;
            background: transparent;
            color: var(--text-color);
            border-radius: 10px;
            cursor: pointer;
            text-align: left;
            width: 100%;
            transition: all 0.2s ease;
            position: relative;
        }

        .section-tab-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        body.dark-mode .section-tab-btn:hover {
            background-color: rgba(255, 255, 255, 0.06);
        }

        .section-tab-btn.active {
            background-color: var(--btn-primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .section-tab-btn i {
            font-size: 18px;
            width: 22px;
            text-align: center;
        }

        .tab-text {
            display: flex;
            flex-direction: column;
            line-height: 1.3;
        }

        .tab-title {
            font-size: 14px;
            font-weight: 600;
        }

        .tab-desc {
            font-size: 11px;
            opacity: 0.75;
            margin-top: 2px;
        }

        /* Right Form Pane */
        .workday-content {
            padding: 35px 40px;
            display: flex;
            flex-direction: column;
        }

        .tab-panel {
            display: none;
            animation: fadeIn 0.25s ease-in-out;
        }

        .tab-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .panel-header {
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .panel-header h3 {
            margin: 0;
            font-size: 20px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-header p {
            margin: 6px 0 0 0;
            font-size: 13px;
            opacity: 0.75;
            color: var(--text-color);
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .full-width {
            grid-column: span 2;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 5px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text-color);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--btn-primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }

        /* Dedicated Password Change Container */
        .password-tool-card {
            background-color: rgba(243, 156, 18, 0.05);
            border: 1px solid rgba(243, 156, 18, 0.3);
            border-left: 5px solid #f39c12;
            padding: 25px;
            border-radius: 12px;
            margin-top: 10px;
        }

        body.dark-mode .password-tool-card {
            background-color: rgba(243, 156, 18, 0.1);
        }

        .password-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-input-wrap input {
            padding-right: 45px;
        }

        .toggle-password-icon {
            position: absolute;
            right: 14px;
            cursor: pointer;
            color: #888;
            transition: color 0.2s;
        }

        .toggle-password-icon:hover {
            color: var(--text-color);
        }

        /* Action Buttons */
        .form-actions {
            margin-top: auto;
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border-color);
        }

        .action-btns-right {
            display: flex;
            gap: 12px;
        }

        .badge-role {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: #e8f4fd;
            color: #2980b9;
        }

        @media (max-width: 950px) {
            .workday-wrapper {
                grid-template-columns: 1fr;
            }
            .workday-nav {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                flex-direction: row;
                overflow-x: auto;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>

    <!-- Main Navigation Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="main-content">
        
        <!-- Header Card -->
        <div class="top-header">
            <div class="greeting-box">
                <h2>
                    <i class="fas fa-user-edit" style="color: var(--btn-primary); margin-right: 8px;"></i> 
                    Edit User Record
                </h2>
                <div class="date-box">
                    Editing <strong><?= htmlspecialchars($user['fullname'] ?? 'User') ?></strong> 
                    &bull; ID: <code><?= htmlspecialchars($user['username'] ?? '') ?></code>
                    <span class="badge-role" style="margin-left: 8px;">
                        <?= htmlspecialchars(str_replace('_', ' ', $user['role'] ?? '')) ?>
                    </span>
                </div>
            </div>
            <a href="admin_manage.php" class="btn btn-view">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>

        <!-- Feedback Messages -->
        <?php if ($msg): ?>
            <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 14px 18px; border-radius: 8px; font-weight: 500; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 14px 18px; border-radius: 8px; font-weight: 500; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Workday-Style Multi-Section Form -->
        <form method="POST" id="editUserForm">
            <div class="workday-wrapper">
                
                <!-- Inner Workday Sidebar -->
                <nav class="workday-nav">
                    <div class="workday-nav-header">Profile Sections</div>
                    
                    <button type="button" class="section-tab-btn active" data-target="tab-account">
                        <i class="fas fa-user-shield"></i>
                        <div class="tab-text">
                            <span class="tab-title">Account & Role</span>
                            <span class="tab-desc">Access & assignments</span>
                        </div>
                    </button>

                    <button type="button" class="section-tab-btn" data-target="tab-personal">
                        <i class="fas fa-id-card"></i>
                        <div class="tab-text">
                            <span class="tab-title">Personal Profile</span>
                            <span class="tab-desc">Name, email, and bio</span>
                        </div>
                    </button>

                    <button type="button" class="section-tab-btn" data-target="tab-academic">
                        <i class="fas fa-graduation-cap"></i>
                        <div class="tab-text">
                            <span class="tab-title">Academic Details</span>
                            <span class="tab-desc">Course, college, section</span>
                        </div>
                    </button>

                    <button type="button" class="section-tab-btn" data-target="tab-ojt">
                        <i class="fas fa-school"></i>
                        <div class="tab-text">
                            <span class="tab-title">OJT & School</span>
                            <span class="tab-desc">Deployment assignment</span>
                        </div>
                    </button>

                    <button type="button" class="section-tab-btn" data-target="tab-security">
                        <i class="fas fa-key"></i>
                        <div class="tab-text">
                            <span class="tab-title">Security & Password</span>
                            <span class="tab-desc">Admin password override</span>
                        </div>
                    </button>
                </nav>

                <!-- Inner Content Pane -->
                <div class="workday-content">
                    
                    <!-- 1. Account & Role -->
                    <div class="tab-panel active" id="tab-account">
                        <div class="panel-header">
                            <h3><i class="fas fa-shield-alt"></i> Account Status & System Permissions</h3>
                            <p>Manage system access level, assigned supervisor, and username.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username / Login ID</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>System Role</label>
                                <select name="role" class="form-control">
                                    <option value="student_teacher" <?= ($user['role'] ?? '') === 'student_teacher' ? 'selected' : '' ?>>Student Teacher</option>
                                    <option value="supervisor" <?= ($user['role'] ?? '') === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
                                    <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Account Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="suspended" <?= ($user['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Assigned Supervisor</label>
                                <select name="supervisor_id" class="form-control">
                                    <option value="0">-- None Assigned --</option>
                                    <?php 
                                    if ($supervisors) {
                                        $supervisors->data_seek(0);
                                        while ($sup = $supervisors->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $sup['id'] ?>" <?= (($user['assigned_supervisor_id'] ?? 0) == $sup['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sup['fullname'] ?? '') ?>
                                        </option>
                                    <?php endwhile; } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Personal Information -->
                    <div class="tab-panel" id="tab-personal">
                        <div class="panel-header">
                            <h3><i class="fas fa-address-card"></i> Personal Information</h3>
                            <p>Update biographical details, contact information, and address.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Birthdate</label>
                                <input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender" class="form-control">
                                    <option value="" disabled>Select Gender...</option>
                                    <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label>Complete Address</label>
                                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- 3. Academic Details -->
                    <div class="tab-panel" id="tab-academic">
                        <div class="panel-header">
                            <h3><i class="fas fa-university"></i> Academic Information</h3>
                            <p>Update college department, enrolled program, and section.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>College / Department</label>
                                <input type="text" name="college" class="form-control" value="<?= htmlspecialchars($user['college'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Course / Program</label>
                                <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($user['course'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Year Level</label>
                                <input type="text" name="year_level" class="form-control" value="<?= htmlspecialchars($user['year_level'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Class Section</label>
                                <input type="text" name="section" class="form-control" value="<?= htmlspecialchars($user['section'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- 4. OJT & Partner School -->
                    <div class="tab-panel" id="tab-ojt">
                        <div class="panel-header">
                            <h3><i class="fas fa-building"></i> Deployment & Partner School</h3>
                            <p>Assign the current cooperating school for practice teaching.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Partner School / Training Institution</label>
                                <input type="text" name="partner_school" class="form-control" value="<?= htmlspecialchars($user['partner_school'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- 5. Dedicated Security & Password Override Container -->
                    <div class="tab-panel" id="tab-security">
                        <div class="panel-header">
                            <h3><i class="fas fa-user-lock"></i> Security & Password Management</h3>
                            <p>Administrative tool to reset or overwrite the user's password.</p>
                        </div>

                        <?php if (!empty($user['reset_request']) &&$user['reset_request'] == 1): ?>
                            <div style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-exclamation-triangle"></i> This user has an active password reset request pending.
                            </div>
                        <?php endif; ?>

                        <div class="password-tool-card">
                            <h4 style="margin: 0 0 10px 0; color: #d35400; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-tools"></i> Admin Password Reset Tool
                            </h4>
                            <p style="margin: 0 0 20px 0; font-size: 13px; opacity: 0.85; line-height: 1.5;">
                                Leave this field blank to keep the current password. Entering a new password will immediately update their credentials and reset any pending reset requests.
                            </p>

                            <div class="form-group" style="max-width: 450px;">
                                <label style="font-weight: 700;">New Password</label>
                                <div class="password-input-wrap">
                                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Leave empty to keep existing password" autocomplete="new-password">
                                    <i class="fas fa-eye toggle-password-icon" id="togglePasswordVisibility" title="Show/Hide Password"></i>
                                </div>
                                <small style="font-size: 12px; color: #888; margin-top: 6px;">
                                    Recommended: Minimum 8 characters with numbers and symbols.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Actions & Stepper Footer -->
                    <div class="form-actions">
                        <a href="admin_manage.php" class="btn btn-remove" style="background-color: #7f8c8d; color: white;">
                            Cancel
                        </a>
                        
                        <div class="action-btns-right">
                            <button type="button" id="prevSectionBtn" class="btn" style="background-color: #95a5a6; color: white; display: none;">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" id="nextSectionBtn" class="btn btn-view">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                            <button type="submit" class="btn" style="background-color: #27ae60; color: white; padding: 10px 24px;">
                                <i class="fas fa-save"></i> Update User Record
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </form>

    </div>

    <!-- Interactive Scripts -->
    <script>
    (function () {
        const tabs = document.querySelectorAll('.section-tab-btn');
        const panels = document.querySelectorAll('.tab-panel');
        const prevBtn = document.getElementById('prevSectionBtn');
        const nextBtn = document.getElementById('nextSectionBtn');
        let currentIndex = 0;

        // Section switching
        function showSection(index) {
            tabs.forEach((tab, i) => tab.classList.toggle('active', i === index));
            panels.forEach((panel, i) => panel.classList.toggle('active', i === index));

            currentIndex = index;
            prevBtn.style.display = index === 0 ? 'none' : 'inline-flex';
            nextBtn.style.display = index === panels.length - 1 ? 'none' : 'inline-flex';
        }

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => showSection(index));
        });

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (currentIndex < panels.length - 1) {
                    showSection(currentIndex + 1);
                }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentIndex > 0) {
                    showSection(currentIndex - 1);
                }
            });
        }

        // Toggle password view
        const toggleIcon = document.getElementById('togglePasswordVisibility');
        const passInput = document.getElementById('new_password');
        if (toggleIcon && passInput) {
            toggleIcon.addEventListener('click', function () {
                const isPassword = passInput.getAttribute('type') === 'password';
                passInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleIcon.classList.toggle('fa-eye', !isPassword);
                toggleIcon.classList.toggle('fa-eye-slash', isPassword);
            });
        }

        // Password Change Confirmation Prompt
        const form = document.getElementById('editUserForm');
        const userFullName = <?= json_encode($user['fullname'] ?? 'this user') ?>;

        form.addEventListener('submit', function (e) {
            const enteredPass = passInput ? passInput.value.trim() : '';
            if (enteredPass !== '') {
                const confirmed = confirm(`Are you sure you want to change the password for "${userFullName}"?\n\nThe previous password will stop working immediately.`);
                if (!confirmed) {
                    e.preventDefault();
                    // Focus on the security tab so the admin can review
                    const securityTabBtn = document.querySelector('[data-target="tab-security"]');
                    if (securityTabBtn) securityTabBtn.click();
                    passInput.focus();
                    return false;
                }
            }
        });
    })();
    </script>
</body>
</html>
