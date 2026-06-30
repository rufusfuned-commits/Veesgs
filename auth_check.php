<?php
session_start();

// Check if this is an admin page requiring authentication
$currentScript = basename($_SERVER['PHP_SELF']);
// Bug #12 fix: Added 'app_admin.php' and 'proxy_admin.php' to the admin pages
// list so they get the admin_logged_in redirect (defense-in-depth).
$adminPages = ['admin.php', 'account_admin.php', 'ban_admin.php', 'game_admin.php', 'app_admin.php', 'proxy_admin.php', 'popup_admin.php', 'profile_admin.php', 'user_report_admin.php', 'admin_tools.php'];

if (in_array($currentScript, $adminPages)) {
  // Admin pages require authentication
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin_login.php');
    exit;
  }
} else {
  // Regular pages - no login required
  if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = 'guest';
    $_SESSION['account_key'] = 'guest';
  }
}

if (file_exists(__DIR__ . '/profile_helpers.php')) {
  require_once __DIR__ . '/profile_helpers.php';
  // Only track online if not a guest user
  if (isset($_SESSION['account_key']) && $_SESSION['account_key'] !== 'guest') {
    profile_touch_online();
  }
}
?>
