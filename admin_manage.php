<?php
// Suppress warnings for a clean user experience
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
session_start();
include 'db_connect.php';

// Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    if($del_id == $_SESSION['user_id']) { echo "<script>alert('Cannot delete own account.');</script>"; }
    else { 
        $conn->query("DELETE FROM users WHERE id=$del_id"); 
        header("Location: admin_manage.php"); exit();
    }
}

// Logic for Filtering Reset Requests
$where_clause = "";
$filter_msg = "";
if(isset($_GET['filter']) && $_GET['filter'] == 'reset') {
    $where_clause = "WHERE reset_request = 1";
    $filter_msg = "Showing only Password Reset Requests";
}

$result = $conn->query("SELECT * FROM users $where_clause ORDER BY reset_request DESC, role DESC, fullname ASC");

// UI Header formatting
$display_name = $_SESSION['fullname'] ?? 'Administrator';
if ($display_name === 'Aljon Timbreza' || $display_name === 'Super Admin') {
    $display_name = 'Administrator';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .role-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: white; display: inline-block; }
        .role-admin { background-color: #c0392b; }
        .role-supervisor { background-color: #8e44ad; }
        .role-student { background-color: #2980b9; }
        .action-cell { white-space: nowrap; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

    <div class="main-content">
        
        <!-- Universal Top Header Banner -->
        <?php include 'header.php'; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 class="header-title" style="margin-bottom:0;">User Management</h2>
            <a href="admin_add_user.php" class="btn btn-view" style="background-color: var(--btn-primary); padding: 10px 20px;"><i class="fas fa-user-plus"></i> Add User</a>
        </div>

        <?php if($filter_msg): ?>
            <div style="background:#fff3cd; color:#856404; padding:10px; margin-bottom:15px; border-left:5px solid #ffc107;">
                <strong>Filter Active:</strong> <?php echo $filter_msg; ?> 
                <a href="admin_manage.php" style="margin-left:15px; color:#856404; font-weight:bold;">Clear Filter</a>
            </div>
        <?php endif; ?>

        <div class="card">
            <table>
                <thead>
                    <tr><th>Full Name</th><th>Email / Contact</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr style="<?php echo (isset($row['reset_request']) && $row['reset_request'] == 1) ? 'background:rgba(231, 76, 60, 0.1);' : ''; ?>">
        <td>
            <strong><?php echo htmlspecialchars($row['fullname'] ?? 'N/A'); ?></strong>
            <?php if(isset($row['reset_request']) && $row['reset_request'] == 1): ?>
                <span style="color:#e74c3c; margin-left:5px;" title="Requested Password Reset">
                    <i class="fas fa-key"></i> <small style="font-weight:bold;">Reset Requested</small>
                </span>
            <?php endif; ?>
            <br><small style="opacity:0.7;">ID: <?php echo htmlspecialchars($row['username'] ?? 'N/A'); ?></small>
        </td>
        <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
        <td>
            <?php 
                $role = $row['role'] ?? 'student';
                $role_class = 'role-student';
                if($role == 'admin') $role_class = 'role-admin';
                if($role == 'supervisor') $role_class = 'role-supervisor';
            ?>
            <span class="role-badge <?php echo $role_class; ?>"><?php echo str_replace('_', ' ', htmlspecialchars($role)); ?></span>
        </td>
        <td>
            <?php 
                // FIXED: Added trim() to strip out hidden spaces causing the "Suspended" bug
                $status = strtolower(trim($row['status'] ?? 'suspended'));
            ?>
            <?php if($status == 'active'): ?>
                <span style="color:#27ae60; font-weight:bold; font-size:12px;">Active</span>
            <?php else: ?>
                <span style="color:#e74c3c; font-weight:bold; font-size:12px;">Suspended</span>
            <?php endif; ?>
        </td>
        <td class="action-cell">
            <a href="edit_user.php?id=<?php echo $row['id'] ?? '0'; ?>" class="btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
            <a href="?delete_id=<?php echo $row['id'] ?? '0'; ?>" class="btn btn-remove" onclick="return confirm('Delete user?');"><i class="fas fa-trash"></i></a>
        </td>
    </tr>
    <?php endwhile; ?>
</tbody>
            </table>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
