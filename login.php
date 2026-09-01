<?php
/**
 * Modern Authentication & Login Screen
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
    <title>Sign In - JLD Apartment Management</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Icons -->
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
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .login-header {
            padding: 2.25rem 2rem 1.5rem 2rem;
            text-align: center;
            background: #ffffff;
        }
        .brand-badge {
            width: 54px;
            height: 54px;
            background: #2563eb;
            color: #ffffff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 16px rgba(37,99,235,0.3);
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
        .btn-login {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 10px;
            border: none;
            transition: background 0.2s;
        }
        .btn-login:hover {
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

<div class="login-card">
    <div class="login-header">
        <div class="brand-badge">
            <i class="fas fa-building"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">JLD Apartment</h4>
        <p class="text-muted small mb-0">Apartment & Rental Management System</p>
    </div>

    <div class="p-4 pt-0">
        <?php renderFlash(); ?>

        <form action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=login" method="POST">
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Username or Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" id="usernameInput" class="form-control border-start-0 ps-0" placeholder="Enter your username or email" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-semibold text-secondary mb-0">Password</label>
                    <a href="<?php echo BASE_URL; ?>forgot_password.php" class="text-decoration-none small text-primary fw-semibold">Forgot Password?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="passwordInput" class="form-control border-start-0 border-end-0 ps-0" placeholder="Enter your password" required>
                    <button class="btn btn-light border border-start-0 text-muted" type="button" id="togglePasswordBtn">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login w-100 mb-2">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>
    </div>

    <!-- Registration Link Box -->
    <div class="auth-footer-box">
        <span class="text-muted small">Don't have an account yet?</span>
        <a href="<?php echo BASE_URL; ?>register.php" class="text-decoration-none fw-bold text-primary small ms-1">Create an Account</a>
    </div>
</div>

<!-- Waiting for Super Admin Approval Modal Popup -->
<?php
$isPendingApproval = isset($_GET['pending_approval']);
$pendingName = $_SESSION['pending_landlord_name'] ?? 'Landlord Applicant';
$pendingEmail = $_SESSION['pending_landlord_email'] ?? '';
?>
<div class="modal fade" id="waitingApprovalModal" tabindex="-1" aria-labelledby="waitingApprovalModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-body text-center p-4 pt-5">
                <div class="mb-3">
                    <div style="width: 72px; height: 72px; background: #fef3c7; color: #d97706; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; border: 3px solid #fde68a;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mb-2" id="waitingApprovalModalLabel">Waiting for Super Admin Approval</h4>
                <p class="text-muted small mb-3">
                    <?php if (!empty($pendingName) && $pendingName !== 'Landlord Applicant'): ?>
                        Hello <strong><?php echo htmlspecialchars($pendingName); ?></strong>! 
                    <?php endif; ?>
                    Your <strong>Property Owner / Landlord</strong> account request has been successfully submitted.
                </p>
                
                <div class="alert alert-warning text-start py-3 px-3 small border-0 mb-4" style="background-color: #fffbeb; border-radius: 12px;">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fas fa-info-circle text-warning fs-5 mt-1"></i>
                        <div>
                            <div class="fw-bold text-dark mb-1">Approval in Progress</div>
                            <div class="text-secondary" style="line-height: 1.45;">
                                A 1-click authorization link has been sent to the <strong>Super Administrator's Gmail</strong>. Please wait for the Super Admin to approve your account.
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-muted small mb-4" style="font-size: 0.8rem;">
                    Once approved by the Super Admin, you will be able to log in immediately with your username and password.
                </p>

                <button type="button" class="btn btn-primary w-100 py-2 fw-semibold" data-bs-dismiss="modal" style="border-radius: 10px;">
                    <i class="fas fa-check me-1"></i> Got it, I'll wait for approval
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById('togglePasswordBtn')?.addEventListener('click', function() {
    const input = document.getElementById('passwordInput');
    const icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

<?php if ($isPendingApproval): ?>
document.addEventListener('DOMContentLoaded', function() {
    const approvalModal = new bootstrap.Modal(document.getElementById('waitingApprovalModal'));
    approvalModal.show();
});
<?php endif; ?>
</script>

</body>
</html>
