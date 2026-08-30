<?php
/**
 * Shared: 403 Forbidden Error Page
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
$pageTitle = '403 Forbidden Access';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden - ResiPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/custom.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="text-center p-5 bg-white rounded-4 shadow-sm border" style="max-width: 500px;">
        <div class="mb-3 text-danger">
            <i class="fas fa-shield-alt fa-4x"></i>
        </div>
        <h2 class="fw-bold text-dark mb-2">403 Access Denied</h2>
        <p class="text-muted small mb-4">
            You do not have the required permissions or role clearance to view this page.
        </p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-primary">
                <i class="fas fa-home me-1"></i> Return to Dashboard
            </a>
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline-secondary">
                <i class="fas fa-sign-out-alt me-1"></i> Sign Out
            </a>
        </div>
    </div>
</body>
</html>
