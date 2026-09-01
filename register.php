<?php
/**
 * User Self-Registration Screen (Tenant / Landlord with Super Admin Approval Code)
 */
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'index.php');
}

$pdo = getDBConnection();
// Fetch Super Admin email to display masked hint
$saStmt = $pdo->query("SELECT email FROM users WHERE role = 'super_admin' LIMIT 1");
$superAdminEmail = $saStmt->fetchColumn() ?: 'superadmin@resipro.ph';

// Mask Super Admin email (e.g. s***n@resipro.ph)
$parts = explode('@', $superAdminEmail);
$namePart = $parts[0];
$domainPart = $parts[1] ?? '';
$maskedSuperAdmin = (strlen($namePart) > 2) ? substr($namePart, 0, 1) . str_repeat('*', strlen($namePart) - 2) . substr($namePart, -1) . '@' . $domainPart : $superAdminEmail;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - ResiPro Apartment Management</title>
    
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
            padding: 2rem 1.5rem;
        }
        .register-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            max-width: 540px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .register-header {
            padding: 2rem 2rem 1.25rem 2rem;
            text-align: center;
            background: #ffffff;
        }
        .brand-badge {
            width: 50px;
            height: 50px;
            background: #2563eb;
            color: #ffffff;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 8px 16px rgba(37,99,235,0.3);
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.65rem 0.95rem;
            border: 1px solid #cbd5e1;
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn-register {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 10px;
            border: none;
            transition: background 0.2s;
        }
        .btn-register:hover {
            background: #1d4ed8;
            color: #ffffff;
        }
        .admin-verification-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 1.15rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s ease;
        }
        .auth-footer-box {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 1.15rem 2rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="register-header">
        <div class="brand-badge">
            <i class="fas fa-user-plus"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Create an Account</h4>
        <p class="text-muted small mb-0">Join ResiPro Apartment Management</p>
    </div>

    <div class="p-4 pt-0">
        <?php renderFlash(); ?>

        <!-- Dynamic feedback alert for AJAX code requests -->
        <div id="ajaxAlertContainer" style="display: none;"></div>

        <form action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=register" method="POST" onsubmit="return validateRegistrationForm()">
            <!-- Account Role Selection -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">I am registering as: <span class="text-danger">*</span></label>
                <select name="role" id="roleSelect" class="form-select fw-semibold" required onchange="toggleRoleVerification(this.value)">
                    <option value="tenant" selected>Tenant (Renting an apartment/unit)</option>
                    <option value="admin">Property Owner / Landlord </option>
                </select>
            </div>

            <!-- Landlord Approval Notice & Code Input (Displayed when Landlord is selected) -->
            <div id="superAdminSection" class="admin-verification-box" style="display: none;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-shield-alt text-warning fs-5"></i>
                    <strong class="text-dark small">Security Authorization Required</strong>
                </div>
                <p class="text-muted small mb-3" style="font-size: 0.82rem; line-height: 1.45;">
                    To register as a Landlord, an approval code must be sent to the <strong>Landlord's Gmail  (<?php echo htmlspecialchars($maskedSuperAdmin); ?>) </strong>.
                </p>

                <div class="d-flex gap-2 align-items-end mb-2">
                    <div class="flex-grow-1">
                        <label class="form-label small fw-semibold text-dark mb-1">Landlord Authorization Code <span class="text-danger">*</span></label>
                        <input type="text" name="admin_code" id="adminCodeInput" class="form-control font-monospace fw-bold text-center" placeholder="••••••" maxlength="6" pattern="[0-9]{6}">
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-dark btn-sm py-2 px-3 fw-semibold text-nowrap" id="requestAdminCodeBtn" onclick="requestSuperAdminCode()">
                            <i class="fas fa-paper-plane me-1"></i> <span id="adminCodeBtnText">Send Code to Super Admin</span>
                        </button>
                    </div>
                </div>
                <div id="codeRequestFeedback" class="small" style="display:none; font-size: 0.78rem;"></div>
            </div>

            <!-- Full Name -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-id-card"></i></span>
                    <input type="text" name="name" id="regName" class="form-control border-start-0 ps-0" placeholder="e.g. Maria Clara Santos" required>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <!-- Username -->
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-secondary">Username <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="e.g. JeckDetera" required>
                    </div>
                </div>
                <!-- Phone -->
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-secondary">Phone Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-phone"></i></span>
                        <input type="text" name="phone" class="form-control border-start-0 ps-0" placeholder="0917-XXX-XXXX" required>
                    </div>
                </div>
            </div>

            <!-- Email Address (Gmail) -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Email Address (Gmail / Active Email) <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" id="regEmail" class="form-control border-start-0 ps-0" placeholder="your.email@gmail.com" required>
                </div>
            </div>

            <div class="row g-2 mb-4">
                <!-- Password -->
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-secondary">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="regPassword" class="form-control border-start-0 ps-0" placeholder="Min. 6 chars" minlength="6" required>
                    </div>
                </div>
                <!-- Confirm Password -->
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-secondary">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock-open"></i></span>
                        <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control border-start-0 ps-0" placeholder="Repeat password" minlength="6" required>
                    </div>
                </div>
            </div>

            <button type="submit" id="btnSubmitRegister" class="btn btn-register w-100 mb-2">
                <i class="fas fa-check-circle me-2"></i><span id="btnSubmitText">Complete Registration</span>
            </button>
        </form>
    </div>

    <!-- Sign In Link Box -->
    <div class="auth-footer-box">
        <span class="text-muted small">Already have an account?</span>
        <a href="<?php echo BASE_URL; ?>login.php" class="text-decoration-none fw-bold text-primary small ms-1">Sign In here</a>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

<script>
let adminOtpTimer = null;
let adminCountdown = 60;

function toggleRoleVerification(role) {
    const adminBox = document.getElementById('superAdminSection');
    const tenantBox = document.getElementById('tenantSection');
    const adminCodeInput = document.getElementById('adminCodeInput');

    if (role === 'admin') {
        adminBox.style.display = 'block';
        tenantBox.style.display = 'none';
        adminCodeInput.required = true;
    } else {
        adminBox.style.display = 'none';
        tenantBox.style.display = 'flex';
        adminCodeInput.required = false;
        adminCodeInput.value = '';
    }
}

function requestSuperAdminCode() {
    const name = document.getElementById('regName').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const btn = document.getElementById('requestAdminCodeBtn');
    const btnText = document.getElementById('adminCodeBtnText');
    const feedback = document.getElementById('codeRequestFeedback');

    if (!name || !email) {
        alert('Please enter your Full Name and Email Address first before requesting the Super Admin code.');
        if (!name) document.getElementById('regName').focus();
        else document.getElementById('regEmail').focus();
        return;
    }

    btn.disabled = true;
    btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    feedback.style.display = 'block';
    feedback.className = 'small text-muted mt-1';
    feedback.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending approval code to Super Admin Gmail...';

    const formData = new FormData();
    formData.append('applicant_name', name);
    formData.append('applicant_email', email);

    fetch('<?php echo BASE_URL; ?>controllers/AuthController.php?action=request_admin_otp', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            feedback.className = 'small text-success fw-medium mt-1';
            feedback.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
            document.getElementById('adminCodeInput').focus();

            adminCountdown = 60;
            btnText.textContent = `Resend (${adminCountdown}s)`;
            clearInterval(adminOtpTimer);
            adminOtpTimer = setInterval(() => {
                adminCountdown--;
                if (adminCountdown > 0) {
                    btnText.textContent = `Resend (${adminCountdown}s)`;
                } else {
                    clearInterval(adminOtpTimer);
                    btn.disabled = false;
                    btnText.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Code to Super Admin';
                }
            }, 1000);
        } else {
            feedback.className = 'small text-danger fw-medium mt-1';
            feedback.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> ' + data.message;
            btn.disabled = false;
            btnText.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Code to Super Admin';
        }
    })
    .catch(err => {
        feedback.className = 'small text-danger fw-medium mt-1';
        feedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> Request failed. Please check your connection.';
        btn.disabled = false;
        btnText.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Code to Super Admin';
    });
}

function validateRegistrationForm() {
    const pass = document.getElementById('regPassword').value;
    const confirm = document.getElementById('regConfirmPassword').value;
    const role = document.getElementById('roleSelect').value;
    const adminCode = document.getElementById('adminCodeInput').value.trim();

    if (pass !== confirm) {
        alert('Passwords do not match! Please check and try again.');
        return false;
    }

    if (pass.length < 6) {
        alert('Password must be at least 6 characters in length.');
        return false;
    }

    if (role === 'admin') {
        if (adminCode === '') {
            alert('Please click "Send Code to Super Admin" and enter the 6-digit approval code provided by the Super Admin.');
            document.getElementById('adminCodeInput').focus();
            return false;
        }
        if (adminCode.length !== 6 || !/^\d{6}$/.test(adminCode)) {
            alert('Please enter a valid 6-digit numeric Super Admin approval code.');
            document.getElementById('adminCodeInput').focus();
            return false;
        }
    }

    const btn = document.getElementById('btnSubmitRegister');
    const btnText = document.getElementById('btnSubmitText');
    btn.disabled = true;
    btnText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';

    return true;
}
</script>

</body>
</html>
