<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable browser and CDN caching for admin views
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header("Location: admin_login.php");
    exit();
}

// Security Check: For admins, verify they are still active and fetch their live status and service permissions
if (isset($conn) && ($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['user_id'])) {
    try {
        $secStmt = $conn->prepare("SELECT is_active, permissions, created_at FROM users WHERE id = :uid LIMIT 1");
        $secStmt->execute(['uid' => $_SESSION['user_id']]);
        $currUser = $secStmt->fetch(PDO::FETCH_ASSOC);

        if (!$currUser || (int)$currUser['is_active'] !== 1) {
            // Admin has been deactivated by Super Admin
            session_unset();
            session_destroy();
            header("Location: login.php?error=" . urlencode("Your administrator account is inactive or has been disabled."));
            exit();
        }

        // Keep session permissions fresh in real time
        $livePerms = [];
        if (!empty($currUser['permissions'])) {
            $dec = json_decode($currUser['permissions'], true);
            if (is_array($dec)) $livePerms = $dec;
        }
        $_SESSION['permissions'] = $livePerms;
        $_SESSION['admin_created_at'] = $currUser['created_at'] ?? null;
    } catch (Throwable $e) {}
}
?>
