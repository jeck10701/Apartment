<?php
/**
 * Root Entry Point - Dynamic Routing
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'tenant';
    if ($role === 'super_admin') {
        redirect(BASE_URL . 'views/super_admin/dashboard.php');
    } elseif ($role === 'admin') {
        redirect(BASE_URL . 'views/admin/dashboard.php');
    } else {
        redirect(BASE_URL . 'views/tenant/dashboard.php');
    }
} else {
    redirect(BASE_URL . 'login.php');
}
