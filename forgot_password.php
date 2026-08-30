<?php
/**
 * Step 1: Forgot Password - Request Verification Code (OTP)
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ResiPro</title>
    
    <!-- Google Fonts & Bootstrap 5.3 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .forgot-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .forgot-header {
            padding: 2.25rem 2rem 1.25rem 2rem;
            text-align: center;
            background: #ffffff;
        }
        .brand-badge {
            width: 54px;
            height: 54px;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #dbeafe;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn-send {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 10px;
            border: none;
            transition: background 0.2s;
        }
        .btn-send:hover {
            background: #1d4ed8;
            color: #ffffff;
        }
        .auth-footer-box {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 1.25rem 2rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="forgot-card">
    <div class="forgot-header">
        <div class="brand-badge">
            <i class="fas fa-key"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Forgot Password?</h4>
        <p class="text-muted small mb-0">Enter your registered email and we'll send a 6-digit verification code.</p>
    </div>

    <div class="p-4 pt-0">
        <?php renderFlash(); ?>

        <form action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=send_otp" method="POST">
            <div class="mb-4">
                <label class="form-label small fw-semibold text-secondary">Email Address (Gmail)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="e.g. maria.santos@gmail.com" required autofocus>
                </div>
                <small class="text-muted" style="font-size: 0.75rem;">Make sure you have access to this email to receive your OTP code.</small>
            </div>

            <button type="submit" class="btn btn-send w-100 mb-2">
                <i class="fas fa-paper-plane me-2"></i>Send Verification Code
            </button>
        </form>
    </div>

    <!-- Back to Login Link -->
    <div class="auth-footer-box">
        <a href="<?php echo BASE_URL; ?>login.php" class="text-decoration-none fw-semibold text-secondary small">
            <i class="fas fa-arrow-left me-1"></i> Back to Sign In
        </a>
    </div>
</div>

</body>
</html>
