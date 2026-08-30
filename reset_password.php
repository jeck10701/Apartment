<?php
/**
 * Step 3: Create New Password after successful OTP Verification
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'index.php');
}

$verifiedEmail = $_SESSION['verified_reset_email'] ?? '';
if (empty($verifiedEmail)) {
    setFlash('warning', 'Please verify your email code first.');
    redirect(BASE_URL . 'forgot_password.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - ResiPro</title>
    
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
        .reset-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .reset-header {
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
        .btn-reset {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 10px;
            border: none;
            transition: background 0.2s;
        }
        .btn-reset:hover {
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

<div class="reset-card">
    <div class="reset-header">
        <div class="brand-badge">
            <i class="fas fa-lock"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Set New Password</h4>
        <p class="text-muted small mb-0">Identity verified for <strong><?php echo htmlspecialchars($verifiedEmail); ?></strong></p>
    </div>

    <div class="p-4 pt-0">
        <?php renderFlash(); ?>

        <form action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=reset_password" method="POST" onsubmit="return validatePasswordMatch()">
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">New Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                    <input type="password" name="new_password" id="newPass" class="form-control border-start-0 ps-0" placeholder="Minimum 6 characters" minlength="6" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold text-secondary">Confirm New Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock-open"></i></span>
                    <input type="password" name="confirm_password" id="confirmPass" class="form-control border-start-0 ps-0" placeholder="Repeat new password" minlength="6" required>
                </div>
            </div>

            <button type="submit" class="btn btn-reset w-100 mb-2">
                <i class="fas fa-save me-2"></i>Update Password & Sign In
            </button>
        </form>
    </div>

    <!-- Back to Login Link -->
    <div class="auth-footer-box">
        <a href="<?php echo BASE_URL; ?>login.php" class="text-decoration-none fw-semibold text-secondary small">
            <i class="fas fa-times-circle me-1"></i> Cancel
        </a>
    </div>
</div>

<script>
function validatePasswordMatch() {
    const pass = document.getElementById('newPass').value;
    const confirm = document.getElementById('confirmPass').value;
    if (pass !== confirm) {
        alert('Passwords do not match! Please check and try again.');
        return false;
    }
    return true;
}
</script>

</body>
</html>
