<?php
/**
 * Global Utility & Helper Functions
 */

/**
 * Format currency to Philippine Peso (e.g. ₱ 8,500.00)
 */
function formatPeso($amount) {
    return '₱ ' . number_format((float)($amount ?? 0), 2, '.', ',');
}

/**
 * Format Date to readable string (e.g. Aug 26, 2026)
 */
function formatDate($date, $format = 'M d, Y') {
    if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '—';
    }
    return date($format, strtotime($date));
}

/**
 * Sanitize user input string
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if a user session is active
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Retrieve current logged-in user array
 */
function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'name'     => $_SESSION['user_name'] ?? 'User',
        'username' => $_SESSION['user_username'] ?? '',
        'email'    => $_SESSION['user_email'] ?? '',
        'role'     => $_SESSION['user_role'] ?? 'tenant',
        'phone'    => $_SESSION['user_phone'] ?? '',
        'avatar'   => $_SESSION['user_avatar'] ?? null
    ];
}

/**
 * Check if the user has any of the given roles
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'] ?? '', $roles);
}

/**
 * Enforce role requirement or redirect
 */
function requireRole($roles) {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
    if (!hasRole($roles)) {
        header("Location: " . BASE_URL . "views/shared/403.php");
        exit;
    }
}

/**
 * Render stylish, consistent UI status badges
 */
function statusBadge($status, $category = 'general') {
    $status = strtolower(trim((string)$status));
    $classes = 'badge px-2 py-1 rounded-pill ';

    switch ($category) {
        case 'unit':
            switch ($status) {
                case 'vacant':
                    return '<span class="' . $classes . 'bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Available</span>';
                case 'occupied':
                    return '<span class="' . $classes . 'bg-primary-subtle text-primary border border-primary-subtle"><i class="fas fa-user-check me-1"></i>Occupied</span>';
                case 'maintenance':
                    return '<span class="' . $classes . 'bg-warning-subtle text-warning border border-warning-subtle"><i class="fas fa-tools me-1"></i>Maintenance</span>';
                case 'reserved':
                    return '<span class="' . $classes . 'bg-info-subtle text-info border border-info-subtle"><i class="fas fa-clock me-1"></i>Reserved</span>';
                default:
                    return '<span class="' . $classes . 'bg-secondary-subtle text-secondary">' . ucfirst($status) . '</span>';
            }

        case 'invoice':
            switch ($status) {
                case 'paid':
                    return '<span class="' . $classes . 'bg-success text-white"><i class="fas fa-check me-1"></i>Paid</span>';
                case 'partially_paid':
                    return '<span class="' . $classes . 'bg-info text-dark"><i class="fas fa-adjust me-1"></i>Partial</span>';
                case 'unpaid':
                    return '<span class="' . $classes . 'bg-warning-subtle text-warning border border-warning-subtle"><i class="fas fa-clock me-1"></i>Unpaid</span>';
                case 'overdue':
                    return '<span class="' . $classes . 'bg-danger text-white"><i class="fas fa-exclamation-triangle me-1"></i>Overdue</span>';
                default:
                    return '<span class="' . $classes . 'bg-secondary text-white">' . ucfirst($status) . '</span>';
            }

        case 'payment':
            switch ($status) {
                case 'confirmed':
                    return '<span class="' . $classes . 'bg-success text-white"><i class="fas fa-check-double me-1"></i>Verified</span>';
                case 'pending_verification':
                    return '<span class="' . $classes . 'bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i>Pending Review</span>';
                case 'rejected':
                    return '<span class="' . $classes . 'bg-danger text-white"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
                default:
                    return '<span class="' . $classes . 'bg-secondary">' . ucfirst($status) . '</span>';
            }

        case 'maintenance':
            switch ($status) {
                case 'completed':
                    return '<span class="' . $classes . 'bg-success text-white"><i class="fas fa-check me-1"></i>Resolved</span>';
                case 'in_progress':
                    return '<span class="' . $classes . 'bg-primary text-white"><i class="fas fa-spinner fa-spin me-1"></i>In Progress</span>';
                case 'pending':
                    return '<span class="' . $classes . 'bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>';
                case 'cancelled':
                    return '<span class="' . $classes . 'bg-secondary text-white"><i class="fas fa-ban me-1"></i>Cancelled</span>';
                default:
                    return '<span class="' . $classes . 'bg-secondary text-white">' . ucfirst($status) . '</span>';
            }

        case 'priority':
            switch ($status) {
                case 'emergency':
                    return '<span class="badge bg-danger text-white px-2 py-1"><i class="fas fa-bolt me-1"></i>Emergency</span>';
                case 'high':
                    return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fas fa-angle-double-up me-1"></i>High</span>';
                case 'medium':
                    return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="fas fa-grip-lines me-1"></i>Medium</span>';
                case 'low':
                    return '<span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1"><i class="fas fa-angle-down me-1"></i>Low</span>';
                default:
                    return '<span class="badge bg-secondary text-white px-2 py-1">' . ucfirst($status) . '</span>';
            }

        default:
            return '<span class="' . $classes . 'bg-secondary text-white">' . ucfirst($status) . '</span>';
    }
}

/**
 * Flash notification helper
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash notification
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash alert banner if one exists
 */
function renderFlash() {
    $flash = getFlash();
    if ($flash) {
        $icon = 'info-circle';
        if ($flash['type'] === 'success') $icon = 'check-circle';
        if ($flash['type'] === 'danger')  $icon = 'exclamation-circle';
        if ($flash['type'] === 'warning') $icon = 'exclamation-triangle';

        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
            <i class="fas fa-' . $icon . ' me-2 fs-5"></i>
            <div>' . htmlspecialchars($flash['message']) . '</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
}

/**
 * Log action into audit_logs table
 */
function logActivity($action, $details = '') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently continue if log table is unavailable
    }
}

/**
 * Fetch a system setting value by key
 */
function getSetting($key, $default = '') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}
