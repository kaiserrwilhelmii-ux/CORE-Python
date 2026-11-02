<?php
// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch live user info (including profile photo and assigned supervisor)
$header_uid = intval($_SESSION['user_id'] ?? 0);$h_user = [
    'fullname'        => $_SESSION['fullname'] ?? 'User',
    'username'        => $_SESSION['username'] ?? '',
    'email'           => $_SESSION['email'] ?? '',
    'role'            => $_SESSION['role'] ?? 'user',
    'gender'          => $_SESSION['gender'] ?? '',
    'profile_pic'     => '',
    'supervisor_name' => ''
];

if (isset($conn) &&$header_uid > 0) {
    // Join users table to get the assigned supervisor's name
    $h_query =$conn->query("SELECT u.fullname, u.username, u.email, u.role, u.gender, u.profile_pic, s.fullname as supervisor_name 
                             FROM users u 
                             LEFT JOIN users s ON u.assigned_supervisor_id = s.id 
                             WHERE u.id = $header_uid LIMIT 1");
    if ($h_query &&$h_query->num_rows > 0) {
        $h_user =$h_query->fetch_assoc();
    }
}

$h_initials   = strtoupper(substr($h_user['fullname'] ?? 'U', 0, 1));
$h_role_label = ucwords(str_replace('_', ' ',$h_user['role'] ?? 'User'));

// Avatar fallback logic
$has_pic = !empty($h_user['profile_pic']);
$gender_clean = strtolower(trim($h_user['gender'] ?? ''));

$avatar_class = "avatar-neutral";
$avatar_icon  = '<i class="fas fa-user-astronaut"></i>';
$avatar_style = "";

if ($has_pic) {
    $avatar_style = "background-image: url('" . htmlspecialchars($h_user['profile_pic']) . "');";
} else {
    if ($gender_clean === 'male') {$avatar_class = "avatar-male";
        $avatar_icon  = '<i class="fas fa-user"></i>';
    } elseif ($gender_clean === 'female') {$avatar_class = "avatar-female";
        $avatar_icon  = '<i class="fas fa-user"></i>';
    } else {
        $avatar_class = "avatar-neutral";
        $avatar_icon  = '<i class="fas fa-user-astronaut"></i>';
    }
}
?>

<!-- Universal Workday-Style Top Banner -->
<div class="top-banner-card">
    <div class="user-intro-wrap">
        <!-- Clickable Avatar leading to profile.php -->
        <a href="profile.php" class="header-avatar-link" title="View & Edit Profile">
            <div class="banner-avatar <?= $avatar_class ?>" id="headerAvatar" style="<?= $avatar_style ?>">
                <?php if (!$has_pic): ?>
                    <?= $avatar_icon ?>
                <?php endif; ?>
            </div>
        </a>

        <div class="user-intro-info">
            <h2><?= htmlspecialchars($h_user['fullname']) ?></h2>
            
            <!-- Metadata Chips Under Name -->
            <div class="user-meta-chips">
                <span class="badge-role"><?= htmlspecialchars($h_role_label) ?></span>
                
                <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($h_user['username']) ?></span>
                
                <?php if (!empty($h_user['email'])): ?>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($h_user['email']) ?></span>
                <?php endif; ?>

                <!-- Supervisor Chip for Student Teachers -->
                <?php if ($h_user['role'] === 'student_teacher'): ?>
                    <span class="supervisor-chip">
                        <i class="fas fa-user-tie"></i> Supervisor: 
                        <strong><?= htmlspecialchars(!empty($h_user['supervisor_name']) ?$h_user['supervisor_name'] : 'Not Assigned Yet') ?></strong>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Real-time Clock (hh:mm:ss) & Dark Mode Toggle -->
    <div class="header-right-actions">
        <div id="headerLiveDate" class="header-date-chip">
            <span id="liveTimeText"><i class="far fa-clock"></i> 00:00:00 AM</span>
            <span id="liveDateText" style="margin-left: 6px; opacity: 0.8;">Loading...</span>
        </div>
        <button type="button" id="themeToggle" class="theme-toggle">
            <i class="fas fa-moon"></i> Dark Mode
        </button>
    </div>
</div>

<style>
/* Universal Banner Styling */
.top-banner-card {
    background: var(--card-bg, #ffffff);
    border-radius: 16px;
    box-shadow: var(--card-shadow, 0 4px 6px rgba(0, 0, 0, 0.08));
    border: 1px solid var(--border-color, #ecf0f1);
    padding: 22px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 18px;
}

.user-intro-wrap {
    display: flex;
    align-items: center;
    gap: 20px;
}

.header-avatar-link {
    text-decoration: none;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.banner-avatar {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #ffffff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    flex-shrink: 0;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
}

.banner-avatar:hover {
    transform: scale(1.06);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

body.dark-mode .banner-avatar {
    border-color: #2d2d2d;
}

/* Gender Fallback Colors */
.avatar-male {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: #ffffff;
}

.avatar-female {
    background: linear-gradient(135deg, #e84393, #fd79a8);
    color: #ffffff;
}

.avatar-neutral {
    background: linear-gradient(135deg, #6c5ce7, #a29bfe);
    color: #ffffff;
}

.banner-avatar i {
    font-size: 28px;
}

.user-intro-info h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-color, #2c3e50);
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

/* Supervisor Chip Styling */
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

.header-right-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-left: auto;
    flex-wrap: wrap;
}

.header-date-chip {
    font-size: 13px;
    color: var(--text-color, #2c3e50);
    font-weight: 600;
    display: flex;
    align-items: center;
}

#liveTimeText {
    letter-spacing: 0.5px;
}

@media (max-width: 768px) {
    .header-right-actions {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<script>
// Real-time ticking clock with hh:mm:ss
(function () {
    function updateHeaderClock() {
        const timeEl = document.getElementById('liveTimeText');
        const dateEl = document.getElementById('liveDateText');
        if (!timeEl || !dateEl) return;

        const now = new Date();
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';

        hours = hours % 12;
        hours = hours ? String(hours).padStart(2, '0') : '12';

        timeEl.innerHTML = `<i class="far fa-clock"></i> ${hours}:${minutes}:${seconds} ${ampm}`;
        dateEl.textContent = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    updateHeaderClock();
    setInterval(updateHeaderClock, 1000);
})();
</script>
