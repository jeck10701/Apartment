<?php
/**
 * Step 2: Verify 6-Digit Email Verification Code (OTP)
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'index.php');
}

$email = $_SESSION['pending_reset_email'] ?? ($_GET['email'] ?? '');
if (empty($email)) {
    setFlash('warning', 'Please enter your email address first.');
    redirect(BASE_URL . 'forgot_password.php');
}

// Mask email for privacy (e.g. j***z@gmail.com)
$parts = explode('@', $email);
$namePart = $parts[0];
$domainPart = $parts[1] ?? '';
$maskedEmail = (strlen($namePart) > 2) ? substr($namePart, 0, 2) . str_repeat('*', strlen($namePart) - 2) . '@' . $domainPart : $email;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Verification Code - ResiPro</title>
    
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
        .verify-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .verify-header {
            padding: 2.25rem 2rem 1.25rem 2rem;
            text-align: center;
            background: #ffffff;
        }
        .brand-badge {
            width: 54px;
            height: 54px;
            background: #ecfdf5;
            color: #059669;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #a7f3d0;
        }
        .otp-input {
            letter-spacing: 0.4em;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #cbd5e1;
        }
        .otp-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn-verify {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 10px;
            border: none;
            transition: background 0.2s;
        }
        .btn-verify:hover {
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

<div class="verify-card">
    <div class="verify-header">
        <div class="brand-badge">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Enter Verification Code</h4>
        <p class="text-muted small mb-0">We sent a 6-digit code to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong></p>
    </div>

    <div class="p-4 pt-0">
        <?php renderFlash(); ?>

        <form action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=verify_otp" method="POST">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

            <div class="mb-4">
                <label class="form-label small fw-semibold text-secondary text-center d-block">6-Digit Security PIN / OTP</label>
                <input type="text" name="otp_code" class="form-control otp-input font-monospace" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" required autofocus>
                <div class="text-center mt-2">
                    <small class="text-muted" style="font-size: 0.75rem;"><i class="fas fa-clock me-1"></i>Code expires in 15 minutes.</small>
                </div>
            </div>

            <button type="submit" class="btn btn-verify w-100 mb-3">
                <i class="fas fa-check-circle me-2"></i>Verify Code & Proceed
            </button>
        </form>

        <!-- Resend Code Form -->
        <form action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=send_otp" method="POST" class="text-center">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <span class="text-muted small">Didn't receive the email?</span>
            <button type="submit" class="btn btn-link p-0 small fw-bold text-primary text-decoration-none">Resend Code</button>
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
