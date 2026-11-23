<?php
$sidebar_unread_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmtUnread = $conn->prepare("
            SELECT COUNT(*) 
            FROM user_notifications 
            WHERE (user_id = :uid OR user_id IN (SELECT id FROM users WHERE role = 'admin') OR user_id IS NULL) 
              AND is_read = 0
        ");
        $stmtUnread->execute(['uid' => $_SESSION['user_id']]);
        $sidebar_unread_count = (int)$stmtUnread->fetchColumn();
    } catch(PDOException $e) {}
}
?>
<aside class="admin-sidebar">
    <div class="sidebar-header" style="padding: 20px 15px 15px; text-align: center;">
        <div style="background: #ffffff; padding: 6px 12px; border-radius: 8px; display: inline-block; margin-bottom: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
            <img src="../assets/images/logo.png" alt="AKC Logo" style="height: 38px; width: auto; display: block;">
        </div>
        <div style="color: #ffffff; font-size: 15px; font-weight: 700; letter-spacing: 0.3px; line-height: 1.3;">
            AKC <span style="color: #60a5fa; font-weight: 800;">Super Admin</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li class="nav-section-label"><i class="fa-solid fa-grid-2"></i> MAIN</li>
            <li class="<?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
            <li class="<?php echo ($current_page == 'monthly_reports') ? 'active' : ''; ?>">
                <a href="monthly_reports.php" style="line-height: 1.35; padding-top: 10px; padding-bottom: 10px;">
                    <i class="fa-solid fa-file-invoice"></i> 
                    <span>Monthly Reports</span>
                </a>
            </li>
            <li class="<?php echo ($current_page == 'admins') ? 'active' : ''; ?>"><a href="admins.php"><i class="fa-solid fa-user-shield"></i> Admin Management</a></li>
            <li class="nav-section-label"><i class="fa-solid fa-eye"></i> SYSTEM VIEW</li>
            <li class="<?php echo ($current_page == 'view_admin_data') ? 'active' : ''; ?>"><a href="view_admin_data.php"><i class="fa-solid fa-arrow-up-right-from-square"></i> View All Admin Data</a></li>
            <li class="<?php echo ($current_page == 'activity_logs') ? 'active' : ''; ?>"><a href="activity_logs.php"><i class="fa-solid fa-clock-rotate-left"></i> Activity Logs</a></li>
            
            <li class="nav-section-label"><i class="fa-solid fa-sliders"></i> SYSTEM</li>
            <li class="<?php echo ($current_page == 'profile') ? 'active' : ''; ?>"><a href="profile_settings.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
            <li class="<?php echo ($current_page == 'notifications') ? 'active' : ''; ?>"><a href="notifications.php"><i class="fa-solid fa-bell"></i> Notifications <span class="sidebar-badge" id="sidebarNotificationBadge" <?php echo ($sidebar_unread_count > 0) ? '' : 'style="display: none;"'; ?>><?php echo $sidebar_unread_count; ?></span></a></li>
            <li><a href="../logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-admin-profile">
            <div class="admin-avatar" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <?php echo strtoupper(substr($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'SA'), 0, 2)); ?>
            </div>
            <div class="admin-profile-info">
                <span class="admin-profile-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Super Admin')); ?></span>
                <span class="admin-profile-role"><i class="fa-solid fa-shield-halved"></i> Super Admin</span>
            </div>
        </div>
    </div>
</aside>
