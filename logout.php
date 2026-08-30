<?php
/**
 * User Logout & Session Destruction
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    logActivity('LOGOUT', 'User logged out.');
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

session_start();
setFlash('info', 'You have been safely signed out.');
header("Location: " . BASE_URL . "login.php");
exit;
