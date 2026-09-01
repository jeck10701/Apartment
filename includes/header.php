<?php
if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
$currentUser = currentUser();
$pageTitle = $pageTitle ?? 'Apartment Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - JLD Apartment</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/custom.css">
</head>
<body>
<div id="app-wrapper">

    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <div id="content-wrapper">

        <header class="top-navbar no-print">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" id="sidebarToggleBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="d-none d-sm-block">
                    <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i><?php echo date('l, F d, Y'); ?></span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">

                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold text-uppercase" style="font-size: 0.75rem;">
                    <i class="fas fa-shield-alt me-1"></i><?php echo str_replace('_', ' ', $currentUser['role'] ?? 'User'); ?>
                </span>


                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none p-0 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <img src="<?php echo BASE_URL . htmlspecialchars($currentUser['avatar']); ?>" alt="Profile" class="rounded-circle border" style="width:40px;height:40px;object-fit:cover;">
                        <?php else: ?>
                            <div class="user-avatar-badge">
                                <?php echo strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-start d-none d-md-block">
                            <div class="fw-bold text-dark small leading-tight"><?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></div>
                        </div>
                        <i class="fas fa-chevron-down text-muted small ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: 10px; min-width: 200px;">
                        <li><h6 class="dropdown-header text-uppercase font-monospace small">Account Center</h6></li>
                        <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>views/shared/profile.php"><i class="fas fa-user-circle text-primary me-2"></i>Profile Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>


        <main class="main-content">
            <?php renderFlash(); ?>
