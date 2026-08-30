<?php
/**
 * Authentication & Role Guard Middleware
 */
require_once dirname(__DIR__) . '/config/config.php';

if (!isLoggedIn()) {
    setFlash('warning', 'Please log in to access this page.');
    redirect(BASE_URL . 'login.php');
}

$userRole = $_SESSION['user_role'] ?? '';
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF']);

// Protect Super Admin views
if (strpos($currentPath, '/views/super_admin/') !== false && $userRole !== 'super_admin') {
    setFlash('danger', 'Access denied. Super Administrator privileges are required.');
    redirect(BASE_URL . 'views/shared/403.php');
}

// Protect Admin / Property Owner views
if (strpos($currentPath, '/views/admin/') !== false && !in_array($userRole, ['admin', 'super_admin'])) {
    setFlash('danger', 'Access denied. Property Owner / Admin privileges are required.');
    redirect(BASE_URL . 'views/shared/403.php');
}

// Protect Tenant views
if (strpos($currentPath, '/views/tenant/') !== false && !in_array($userRole, ['tenant', 'admin', 'super_admin'])) {
    setFlash('danger', 'Access denied. Tenant account required.');
    redirect(BASE_URL . 'views/shared/403.php');
}
