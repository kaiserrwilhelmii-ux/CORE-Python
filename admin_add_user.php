<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: index.php"); 
    exit(); 
}

$msg = ""; 
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $_POST['role'];
    $status   = $_POST['status'];
    
    // Academic & OJT Fields
    $college        = trim($_POST['college']);
    $course         = trim($_POST['course']);
    $year_level     = trim($_POST['year_level']);
    $section        = trim($_POST['section']);
    $birthdate      = $_POST['birthdate'];
    $gender         = $_POST['gender'];
    $address        = trim($_POST['address']);
    $partner_school = trim($_POST['partner_school']);

    $age = 0;
    if (!empty($birthdate)) {
        $dob = new DateTime($birthdate);
        $now = new DateTime('today');
        $age = $dob->diff($now)->y;
    }

    $stmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, role, status, college, course, year_level, section, birthdate, age, gender, address, partner_school) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssisss", $username, $fullname, $email, $password, $role, $status, $college, $course, $year_level, $section, $birthdate, $age, $gender, $address, $partner_school);
    
    if ($stmt->execute()) { 
        $msg = "User successfully registered in the system!"; 
    } else { 
        $error = "Error adding user: " . $conn->error; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User | CORE System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Workday-Style Container */
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

        /* Form Controls */
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

        /* Form Action Footer */
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

        /* Alerts */
        .alert-box {
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        <!-- Top Header Card -->
        <div class="top-header">
            <div class="greeting-box">
                <h2><i class="fas fa-user-plus" style="color: var(--btn-primary); margin-right: 8px;"></i> Add New User</h2>
                <div class="date-box">Configure system credentials, personal information, and academic assignments</div>
            </div>
            <a href="admin_manage.php" class="btn btn-view">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>

        <!-- Feedback Messages -->
        <?php if ($msg): ?>
            <div class="alert-box alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-box alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Workday-Style Multi-Section Card -->
        <form method="POST" id="addUserForm">
            <div class="workday-wrapper">
                
                <!-- Inner Section Navigation (Workday Sidebar) -->
                <nav class="workday-nav">
                    <div class="workday-nav-header">Account Sections</div>
                    
                    <button type="button" class="section-tab-btn active" data-target="section-account">
                        <i class="fas fa-key"></i>
                        <div class="tab-text">
                            <span class="tab-title">Account & Security</span>
                            <span class="tab-desc">Login & access roles</span>
                        </div>
                    </button>

                    <button type="button" class="section-tab-btn" data-target="section-personal">
                        <i class="fas fa-id-card"></i>
                        <div class="tab-text">
                            <span class="tab-title">Personal Profile</span>
                            <span class="tab-desc">Bio, contact & address</span>
                        </div>
                    </button>

                    <button type="button" class="section-tab-btn" data-target="section-academic">
                        <i class="fas fa-graduation-cap"></i>
                        <div class="tab-text">
                            <span class="tab-title">Academic Details</span>
                            <span class="tab-desc">College, course & year</span>
                        </div>
                    </button>

                    <button type="button" class="section-tab-btn" data-target="section-ojt">
                        <i class="fas fa-school"></i>
                        <div class="tab-text">
                            <span class="tab-title">OJT & Partner School</span>
                            <span class="tab-desc">Deployment assignment</span>
                        </div>
                    </button>
                </nav>

                <!-- Inner Content Pane -->
                <div class="workday-content">
                    
                    <!-- 1. Account & Security -->
                    <div class="tab-panel active" id="section-account">
                        <div class="panel-header">
                            <h3><i class="fas fa-shield-alt"></i> Account Credentials & Access</h3>
                            <p>Set up authentication details and system permissions for this account.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Username</label>
                                <input type="text" name="username" class="form-control" placeholder="e.g. jdoe2026" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Temporary Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user-tag"></i> System Role</label>
                                <select name="role" class="form-control">
                                    <option value="student_teacher">Student Teacher</option>
                                    <option value="supervisor">Supervisor (Cooperating Teacher)</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-toggle-on"></i> Account Status</label>
                                <select name="status" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Personal Profile -->
                    <div class="tab-panel" id="section-personal">
                        <div class="panel-header">
                            <h3><i class="fas fa-address-card"></i> Personal Information</h3>
                            <p>Provide contact details and demographic information.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="fullname" class="form-control" placeholder="Firstname M. Lastname" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="name@domain.com" required>
                            </div>
                            <div class="form-group">
                                <label>Birthdate</label>
                                <input type="date" name="birthdate" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender" class="form-control">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label>Complete Address</label>
                                <input type="text" name="address" class="form-control" placeholder="House No., Street, Barangay, City, Province" required>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Academic Details -->
                    <div class="tab-panel" id="section-academic">
                        <div class="panel-header">
                            <h3><i class="fas fa-university"></i> Academic Information</h3>
                            <p>Assign college department, degree program, and current level.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>College / Department</label>
                                <input type="text" name="college" class="form-control" placeholder="e.g. College of Education" required>
                            </div>
                            <div class="form-group">
                                <label>Course / Degree Program</label>
                                <input type="text" name="course" class="form-control" placeholder="e.g. BTVTED - CSS" required>
                            </div>
                            <div class="form-group">
                                <label>Year Level</label>
                                <input type="text" name="year_level" class="form-control" placeholder="e.g. 3rd Year" required>
                            </div>
                            <div class="form-group">
                                <label>Class Section</label>
                                <input type="text" name="section" class="form-control" placeholder="e.g. Section A" required>
                            </div>
                        </div>
                    </div>

                    <!-- 4. OJT & Partner School -->
                    <div class="tab-panel" id="section-ojt">
                        <div class="panel-header">
                            <h3><i class="fas fa-building"></i> Deployment & Partner School</h3>
                            <p>Assign the designated practice teaching or cooperating school.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Partner School / Training Institution</label>
                                <input type="text" name="partner_school" class="form-control" placeholder="e.g. Pasig City Science High School" required>
                            </div>
                        </div>
                    </div>

                    <!-- Actions & Stepper Footer -->
                    <div class="form-actions">
                        <button type="button" id="prevSectionBtn" class="btn" style="background-color: #95a5a6; color: white; display: none;">
                            <i class="fas fa-arrow-left"></i> Previous Section
                        </button>
                        
                        <div class="action-btns-right" style="margin-left: auto;">
                            <button type="button" id="nextSectionBtn" class="btn btn-view">
                                Next Section <i class="fas fa-arrow-right"></i>
                            </button>
                            <button type="submit" class="btn" style="background-color: #27ae60; color: white; padding: 10px 24px;">
                                <i class="fas fa-save"></i> Save & Add User
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </form>

    </div>

    <!-- Client-Side Section Switching Script -->
    <script>
    (function () {
        const tabs = document.querySelectorAll('.section-tab-btn');
        const panels = document.querySelectorAll('.tab-panel');
        const prevBtn = document.getElementById('prevSectionBtn');
        const nextBtn = document.getElementById('nextSectionBtn');
        let currentIndex = 0;

        function showSection(index) {
            tabs.forEach((tab, i) => {
                tab.classList.toggle('active', i === index);
            });
            panels.forEach((panel, i) => {
                panel.classList.toggle('active', i === index);
            });

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
    })();
    </script>
</body>
</html>
