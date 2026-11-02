<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

$user_id = intval($_SESSION['user_id']);
$role    = $_SESSION['role'] ?? 'student_teacher'; 
$msg     = "";
$error   = "";

// 1. Fetch user data with Supervisor Join
$sql_user = "SELECT u.*, s.fullname as supervisor_name FROM users u LEFT JOIN users s ON u.assigned_supervisor_id = s.id WHERE u.id = $user_id";
$user_res = $conn->query($sql_user);
$user = $user_res ? $user_res->fetch_assoc() : [];
$initials = strtoupper(substr($user['fullname'] ?? 'U', 0, 1));

// 2. Handle Profile Updates
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname  = trim($_POST['fullname'] ?? '');
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL;
    $gender    = $_POST['gender'] ?? '';
    $address   = trim($_POST['address'] ?? '');
    
    // Calculate age from birthdate
    $age = !empty($_POST['age']) ? intval($_POST['age']) : 0;
    if (!empty($birthdate)) {
        try {
            $dob = new DateTime($birthdate);
            $now = new DateTime('today');
            $age = $dob->diff($now)->y;
        } catch(Exception $e) {
            $age = intval($_POST['age'] ?? 0);
        }
    }
    
    $profile_pic_path = $user['profile_pic'] ?? NULL; 
    
    // Handle Photo Removal
    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
        if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) { 
            unlink($user['profile_pic']); 
        }
        $profile_pic_path = NULL; 
    } 
    // Handle Camera Snapshot (Base64)
    elseif (!empty($_POST['captured_image_data'])) {
        $img = $_POST['captured_image_data'];
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        $target_dir = "uploads/profiles/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $target_file = $target_dir . "user_" . $user_id . "_" . time() . ".png";
        if (file_put_contents($target_file, $data)) {
            if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) { 
                unlink($user['profile_pic']); 
            }
            $profile_pic_path = $target_file;
        }
    }
    // Handle Standard File Upload
    elseif (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "uploads/profiles/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_extension = pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . "user_" . $user_id . "_" . time() . "." . $file_extension;
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) { 
                unlink($user['profile_pic']); 
            }
            $profile_pic_path = $target_file;
        }
    }

    // 3. Update Database
    if ($role == 'student_teacher') {
        $course     = trim($_POST['course'] ?? '');
        $college    = trim($_POST['college'] ?? '');
        $year_level = trim($_POST['year_level'] ?? '');
        $section    = trim($_POST['section'] ?? '');
        $sql = "UPDATE users SET fullname=?, age=?, gender=?, birthdate=?, address=?, course=?, college=?, year_level=?, section=?, profile_pic=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssssssi", $fullname, $age, $gender, $birthdate, $address, $course, $college, $year_level, $section, $profile_pic_path, $user_id);
    } else {
        $sql = "UPDATE users SET fullname=?, age=?, gender=?, birthdate=?, address=?, profile_pic=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssi", $fullname, $age, $gender, $birthdate, $address, $profile_pic_path, $user_id);
    }
    
    if ($stmt->execute()) {
        $msg = "Profile updated successfully!";
        $_SESSION['fullname'] = $fullname;
        $user_res = $conn->query($sql_user);
        $user = $user_res ? $user_res->fetch_assoc() : [];
        $initials = strtoupper(substr($user['fullname'] ?? 'U', 0, 1));
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Workday Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Top Profile Summary Header */
        .profile-banner-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .user-intro-wrap {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .banner-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.12);
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
        }

        .user-intro-info h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-color);
        }

        .user-meta-chips {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 6px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #888;
        }

        .badge-role {
            background-color: #e8f4fd;
            color: #2980b9;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        body.dark-mode .badge-role {
            background-color: rgba(52, 152, 219, 0.2);
            color: #a8c7fa;
        }

        .supervisor-chip {
            background-color: rgba(46, 204, 113, 0.12);
            color: #27ae60;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        body.dark-mode .supervisor-chip {
            background-color: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        /* Workday Container Layout */
        .workday-wrapper {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            min-height: 550px;
        }

        /* Inner Left Navigation */
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

        .form-control:disabled, .form-control[readonly] {
            opacity: 0.7;
            background-color: rgba(0,0,0,0.03);
            cursor: not-allowed;
        }

        /* Photo Studio Area */
        .photo-studio-wrap {
            display: flex;
            align-items: center;
            gap: 30px;
            background: rgba(52, 152, 219, 0.04);
            border: 1px solid rgba(52, 152, 219, 0.2);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .photo-preview-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #2c3e50;
            color: #ffffff;
            font-size: 48px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            background-size: cover;
            background-position: center;
            border: 4px solid var(--card-bg);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }

        .photo-studio-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Camera Modal Overlay */
        #cameraModal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        #videoPreview {
            width: 90%;
            max-width: 450px;
            border-radius: 16px;
            border: 4px solid #ffffff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .cam-controls {
            margin-top: 20px;
            display: flex;
            gap: 15px;
        }

        /* Footer Actions */
        .form-actions {
            margin-top: auto;
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border-color);
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

    <!-- Camera Modal Dialog -->
    <div id="cameraModal">
        <video id="videoPreview" autoplay playsinline></video>
        <div class="cam-controls">
            <button type="button" class="btn" style="background-color: #2ecc71; color: white; border-radius: 50px; padding: 12px 28px;" onclick="takeSnapshot()">
                <i class="fas fa-camera"></i> Capture Snapshot
            </button>
            <button type="button" class="btn" style="background-color: #e74c3c; color: white; border-radius: 50px; padding: 12px 28px;" onclick="stopCamera()">
                Cancel
            </button>
        </div>
        <canvas id="captureCanvas" style="display:none;"></canvas>
    </div>

    <!-- Main Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="main-content">
        
        <!-- Top Profile Summary Banner -->
        <div class="profile-banner-card">
            <div class="user-intro-wrap">
                <div class="banner-avatar" 
                     id="bannerAvatar"
                     style="<?php if(!empty($user['profile_pic'])) echo "background-image: url('".$user['profile_pic']."');"; ?>">
                    <?php if(empty($user['profile_pic'])) echo $initials; ?>
                </div>
                <div class="user-intro-info">
                    <h2><?= htmlspecialchars($user['fullname'] ?? 'My Profile') ?></h2>
                    <div class="user-meta-chips">
                        <span class="badge-role"><?= htmlspecialchars(str_replace('_', ' ', $role)) ?></span>
                        <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($user['username'] ?? '') ?></span>
                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email'] ?? '') ?></span>
                        
                        <!-- Prominent Supervisor Chip for Student Teachers -->
                        <?php if ($role === 'student_teacher'): ?>
                            <span class="supervisor-chip">
                                <i class="fas fa-user-tie"></i> Supervisor: <strong><?= htmlspecialchars(!empty($user['supervisor_name']) ? $user['supervisor_name'] : 'Not Assigned Yet') ?></strong>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <button id="themeToggle" class="theme-toggle">
                <i class="fas fa-moon"></i> Dark Mode
            </button>
        </div>

        <!-- Success/Error Feedback -->
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

        <!-- Workday-Style Modular Profile Form -->
        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <!-- Hidden inputs for photo handling -->
            <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" style="display: none;" onchange="previewImage(event)">
            <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">
            <input type="hidden" name="captured_image_data" id="capturedImageData">

            <div class="workday-wrapper">
                
                <!-- Workday Left Navigation Tabs -->
                <nav class="workday-nav">
                    <div class="workday-nav-header">Profile Sections</div>
                    
                    <button type="button" class="section-tab-btn active" data-target="tab-photo">
                        <i class="fas fa-camera-retro"></i>
                        <div class="tab-text">
                            <span class="tab-title">Profile Photo</span>
                            <span class="tab-desc">Camera & upload studio</span>
                        </div>
                    </button>

                    <button type="button" class="section-tab-btn" data-target="tab-personal">
                        <i class="fas fa-user-circle"></i>
                        <div class="tab-text">
                            <span class="tab-title">Personal Details</span>
                            <span class="tab-desc">Name, birthdate, address</span>
                        </div>
                    </button>

                    <?php if ($role === 'student_teacher'): ?>
                    <button type="button" class="section-tab-btn" data-target="tab-academic">
                        <i class="fas fa-graduation-cap"></i>
                        <div class="tab-text">
                            <span class="tab-title">Academic Profile</span>
                            <span class="tab-desc">College, course, section</span>
                        </div>
                    </button>
                    <?php endif; ?>

                    <button type="button" class="section-tab-btn" data-target="tab-account">
                        <i class="fas fa-shield-alt"></i>
                        <div class="tab-text">
                            <span class="tab-title">System Account</span>
                            <span class="tab-desc">Login ID & supervisor</span>
                        </div>
                    </button>
                </nav>

                <!-- Workday Right Content Pane -->
                <div class="workday-content">
                    
                    <!-- 1. Profile Photo Studio -->
                    <div class="tab-panel active" id="tab-photo">
                        <div class="panel-header">
                            <h3><i class="fas fa-camera"></i> Profile Photo Studio</h3>
                            <p>Upload your formal portrait photo or capture a live snapshot using your webcam.</p>
                        </div>

                        <div class="photo-studio-wrap">
                            <div class="photo-preview-large" 
                                 id="avatarPreview"
                                 style="<?php if(!empty($user['profile_pic'])) echo "background-image: url('".$user['profile_pic']."');"; ?>">
                                <?php if(empty($user['profile_pic'])) echo $initials; ?>
                            </div>

                            <div class="photo-studio-actions">
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-view" onclick="openCamera()">
                                        <i class="fas fa-video"></i> Use Webcam
                                    </button>
                                    <button type="button" class="btn" style="background-color: #34495e; color: white;" onclick="document.getElementById('profilePicInput').click();">
                                        <i class="fas fa-upload"></i> Upload Image
                                    </button>
                                    <button type="button" class="btn btn-remove" id="removePhotoBtn" onclick="removePhoto()" style="<?php if(empty($user['profile_pic'])) echo 'display: none;'; ?>">
                                        <i class="fas fa-trash"></i> Remove Photo
                                    </button>
                                </div>
                                <small style="color: #888; font-size: 12px; line-height: 1.4;">
                                    Accepted formats: PNG, JPG, JPEG, WEBP. For optimal results, ensure good lighting and a plain background.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Personal Information -->
                    <div class="tab-panel" id="tab-personal">
                        <div class="panel-header">
                            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                            <p>Keep your contact and demographic details up to date.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Full Legal Name</label>
                                <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Birthdate</label>
                                <input type="date" id="birthdate" name="birthdate" class="form-control" value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Age</label>
                                <input type="number" id="age" name="age" class="form-control" value="<?= htmlspecialchars($user['age'] ?? '') ?>">
                            </div>
                            <div class="form-group full-width">
                                <label>Gender</label>
                                <select name="gender" class="form-control">
                                    <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label>Permanent Residential Address</label>
                                <input type="text" name="address" class="form-control" placeholder="House No., Street, Barangay, City, Province" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- 3. Academic Information (Student Teachers) -->
                    <?php if ($role === 'student_teacher'): ?>
                    <div class="tab-panel" id="tab-academic">
                        <div class="panel-header">
                            <h3><i class="fas fa-graduation-cap"></i> Academic Details</h3>
                            <p>Information on your current degree program and class section.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>College / Faculty</label>
                                <input type="text" name="college" class="form-control" value="<?= htmlspecialchars($user['college'] ?? '') ?>">
                            </div>
                            <div class="form-group full-width">
                                <label>Course / Program</label>
                                <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($user['course'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Year Level</label>
                                <input type="text" name="year_level" class="form-control" value="<?= htmlspecialchars($user['year_level'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Section</label>
                                <input type="text" name="section" class="form-control" value="<?= htmlspecialchars($user['section'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 4. System Account & Deployment -->
                    <div class="tab-panel" id="tab-account">
                        <div class="panel-header">
                            <h3><i class="fas fa-shield-alt"></i> System Account Overview</h3>
                            <p>System credentials and institutional supervision details.</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username / Login ID (Read-only)</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Registered Email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                            </div>
                            
                            <?php if ($role === 'student_teacher'): ?>
                            <div class="form-group full-width" style="background: rgba(52, 152, 219, 0.08); padding: 15px 20px; border-radius: 10px; border-left: 4px solid var(--btn-primary); margin-top: 15px;">
                                <label style="color: var(--btn-primary); font-weight: 700; margin-bottom: 4px;">
                                    <i class="fas fa-user-tie"></i> Assigned Supervising Teacher (Cooperating Teacher)
                                </label>
                                <div style="font-size: 15px; font-weight: 600; color: var(--text-color);">
                                    <?= htmlspecialchars(!empty($user['supervisor_name']) ? $user['supervisor_name'] : 'Not Assigned Yet') ?>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label>Practice Teaching Partner School</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['partner_school'] ?? 'Unassigned') ?>" disabled>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions & Stepper Footer -->
                    <div class="form-actions">
                        <button type="button" id="prevSectionBtn" class="btn" style="background-color: #95a5a6; color: white; display: none;">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        
                        <div class="action-btns-right" style="margin-left: auto; display: flex; gap: 12px;">
                            <button type="button" id="nextSectionBtn" class="btn btn-view">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                            <button type="submit" class="btn" style="background-color: #27ae60; color: white; padding: 10px 24px;">
                                <i class="fas fa-save"></i> Save Profile
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </form>

    </div>

    <!-- Scripts: Stepper, Age Calculator & Camera Studio -->
    <script>
    // --- 1. Workday Section Stepper ---
    (function () {
        const tabs = document.querySelectorAll('.section-tab-btn');
        const panels = document.querySelectorAll('.tab-panel');
        const prevBtn = document.getElementById('prevSectionBtn');
        const nextBtn = document.getElementById('nextSectionBtn');
        let currentIndex = 0;

        function showSection(index) {
            tabs.forEach((tab, i) => tab.classList.toggle('active', i === index));
            panels.forEach((panel, i) => panel.classList.toggle('active', i === index));

            currentIndex = index;
            if (prevBtn) prevBtn.style.display = index === 0 ? 'none' : 'inline-flex';
            if (nextBtn) nextBtn.style.display = index === panels.length - 1 ? 'none' : 'inline-flex';
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

        // --- 2. Auto Age Calculation on Birthdate Change ---
        const bdateInput = document.getElementById('birthdate');
        const ageInput = document.getElementById('age');
        if (bdateInput && ageInput) {
            bdateInput.addEventListener('change', function () {
                if (!this.value) return;
                const birthDate = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                ageInput.value = age >= 0 ? age : 0;
            });
        }
    })();

    // --- 3. Camera & Photo Management ---
    let cameraStream = null;

    async function openCamera() {
        const modal = document.getElementById('cameraModal');
        modal.style.display = 'flex';
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } });
            document.getElementById('videoPreview').srcObject = cameraStream;
        } catch (err) {
            alert("Unable to access camera. Please check your browser permissions.");
            stopCamera();
        }
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        document.getElementById('cameraModal').style.display = 'none';
    }

    function takeSnapshot() {
        const canvas = document.getElementById('captureCanvas');
        const video = document.getElementById('videoPreview');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('capturedImageData').value = dataUrl;
        
        document.getElementById('avatarPreview').style.backgroundImage = `url(${dataUrl})`;
        document.getElementById('avatarPreview').innerHTML = '';
        document.getElementById('bannerAvatar').style.backgroundImage = `url(${dataUrl})`;
        document.getElementById('bannerAvatar').innerHTML = '';
        
        document.getElementById('removePhotoBtn').style.display = 'inline-flex';
        document.getElementById('removePhotoFlag').value = '0';
        stopCamera();
    }

    function previewImage(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
            document.getElementById('avatarPreview').style.backgroundImage = `url(${reader.result})`;
            document.getElementById('avatarPreview').innerHTML = '';
            document.getElementById('bannerAvatar').style.backgroundImage = `url(${reader.result})`;
            document.getElementById('bannerAvatar').innerHTML = '';
            document.getElementById('removePhotoBtn').style.display = 'inline-flex';
            document.getElementById('removePhotoFlag').value = '0';
        };
        reader.readAsDataURL(file);
    }

    function removePhoto() {
        const initials = <?= json_encode($initials) ?>;
        document.getElementById('avatarPreview').style.backgroundImage = 'none';
        document.getElementById('avatarPreview').innerHTML = initials;
        document.getElementById('bannerAvatar').style.backgroundImage = 'none';
        document.getElementById('bannerAvatar').innerHTML = initials;
        
        document.getElementById('removePhotoFlag').value = '1';
        document.getElementById('capturedImageData').value = '';
        document.getElementById('profilePicInput').value = '';
        document.getElementById('removePhotoBtn').style.display = 'none';
    }
    </script>
</body>
</html>
