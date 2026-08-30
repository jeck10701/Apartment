<?php
/**
 * Shared: User Profile Settings & Security
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Account Profile';
$userId = $_SESSION['user_id'];

// Fetch latest user details from DB
$uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

// Mask email for security display (e.g., je***7@gmail.com)
$userEmail = $user['email'] ?? '';
$parts = explode('@', $userEmail);
$namePart = $parts[0] ?? '';
$domainPart = $parts[1] ?? '';
$maskedEmail = (strlen($namePart) > 2) ? substr($namePart, 0, 2) . str_repeat('*', max(1, strlen($namePart) - 2)) . '@' . $domainPart : $userEmail;

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">My Account Profile</h1>
        <p class="page-subtitle">Update your personal contact information and manage your login password securely.</p>
    </div>
</div>

<div class="row g-4">
    <!-- User Overview Card -->
    <div class="col-12 col-md-4">
        <div class="custom-card text-center p-4">
            <div class="mx-auto mb-3 position-relative" style="width: 88px; height: 88px;">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($user['avatar']); ?>" alt="Profile Picture" id="profileAvatarPreview" class="rounded-circle border shadow-sm" style="width:88px;height:88px;object-fit:cover;">
                <?php else: ?>
                    <div class="user-avatar-badge mx-auto" id="profileAvatarFallback" style="width: 88px; height: 88px; font-size: 2rem;">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($user['name']); ?></h5>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="editProfileBtn" onclick="toggleProfileEditor()">
                    <i class="fas fa-pen me-1"></i> Edit
                </button>
            </div>
            <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill text-uppercase mb-3" style="font-size: 0.75rem;">
                <?php echo str_replace('_', ' ', $user['role']); ?>
            </div>
            <div class="text-muted small border-top pt-3 text-start">
                <div class="mb-2"><i class="fas fa-user-circle me-2 text-muted"></i>Username: <strong><?php echo htmlspecialchars($user['username']); ?></strong></div>
                <div class="mb-2"><i class="fas fa-envelope me-2 text-muted"></i>Email: <strong><?php echo htmlspecialchars($user['email']); ?></strong></div>
                <div class="mb-0"><i class="fas fa-phone me-2 text-muted"></i>Phone: <strong><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></strong></div>
            </div>
        </div>

        <div class="custom-card mt-3 p-3" id="profilePictureEditor" style="display:none;">
            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-camera text-primary me-2"></i>Profile Picture</h6>
            <p class="text-muted small mb-3">Choose a photo, then use Edit / Crop to position it properly before saving. JPG, PNG, or WEBP up to 2 MB.</p>
            <form id="avatarForm" action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=upload_avatar" method="POST" enctype="multipart/form-data">
                <input type="file" name="avatar" id="avatarInput" class="form-control form-control-sm mb-2" accept="image/jpeg,image/png,image/webp" required>
                <input type="hidden" name="cropped_avatar" id="croppedAvatar">
                <div id="avatarPreviewBox" class="mb-2 text-center" style="display:none;">
                    <img id="avatarPreview" src="#" alt="Preview" class="rounded-circle border" style="width:72px;height:72px;object-fit:cover;">
                </div>
                <button type="button" id="editCropBtn" class="btn btn-outline-secondary btn-sm w-100 mb-2" style="display:none;"><i class="fas fa-crop-alt me-1"></i> Edit / Crop Photo</button>
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-upload me-1"></i> Save Profile Picture</button>
            </form>
        </div>

        <div class="custom-card mt-3 p-3 bg-light border-0">
            <div class="d-flex align-items-center gap-2 text-primary fw-semibold small mb-2">
                <i class="fas fa-shield-alt"></i> Security Authentication
            </div>
            <p class="text-muted small mb-0">
                To protect your account, any password changes require both your <strong>Current Password</strong> and a <strong>6-Digit Verification Code (OTP)</strong> sent to your email.
            </p>
        </div>

        <?php if (in_array($user['role'], ['admin', 'tenant'], true)): ?>
        <div class="custom-card mt-3 p-3 border-danger-subtle">
            <h6 class="fw-bold text-danger mb-2"><i class="fas fa-user-times me-2"></i>Delete Account</h6>
            <p class="text-muted small mb-3">Permanently remove your account and sign out. This action cannot be undone.</p>
            <button type="button" class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#deleteMyAccountModal">
                <i class="fas fa-trash-alt me-1"></i> Delete My Account
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Edit Profile & Security Form -->
    <div class="col-12 col-md-8" id="profileEditorPanel" style="display:none;">
        <div class="custom-card">
            <div class="custom-card-header bg-light">
                <h5 class="custom-card-title"><i class="fas fa-user-edit text-primary me-2"></i>Edit Profile & Security Settings</h5>
            </div>
            <div class="custom-card-body">
                <form id="profileForm" action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=update_profile" method="POST" onsubmit="return validateProfileForm()">
                    <h6 class="fw-bold text-dark mb-3">Personal Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="profileEmail" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Mobile Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="0917-XXX-XXXX">
                        </div>
                    </div>

                    <div class="border-top pt-3 mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <h6 class="fw-bold text-dark mb-0">Change Password & Authentication</h6>
                            <span class="badge bg-secondary-subtle text-secondary small">Optional</span>
                        </div>
                        <small class="text-muted d-block mb-3">Leave blank if you do not want to change your password.</small>

                        <div class="alert alert-info py-2 px-3 small border-0 d-flex align-items-start gap-2 mb-3">
                            <i class="fas fa-info-circle text-info mt-1"></i>
                            <div>
                                To change your password, enter your <strong>Current Password</strong>, click <strong>"Send OTP"</strong> to receive an authentication code at <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>, and enter the code below.
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Current Password</label>
                                <input type="password" name="current_password" id="current_password" class="form-control" placeholder="••••••••">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">
                                    Email Verification Code (OTP)
                                </label>
                                <div class="input-group">
                                    <input type="text" name="otp_code" id="otp_code" class="form-control font-monospace fw-bold text-center" placeholder="••••••" maxlength="6" pattern="[0-9]{6}">
                                    <button class="btn btn-outline-primary fw-semibold" type="button" id="btnSendOtp">
                                        <i class="fas fa-paper-plane me-1"></i> <span id="btnSendOtpText">Send OTP</span>
                                    </button>
                                </div>
                                <div id="otpFeedback" class="small mt-1" style="display: none;"></div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Minimum 6 characters" minlength="6">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Repeat new password" minlength="6">
                            </div>
                        </div>
                    </div>

                    <div class="text-end d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light border px-4" onclick="toggleProfileEditor(false)">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i> Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Profile Picture Crop Modal -->
<div class="modal fade" id="cropAvatarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-crop-alt text-primary me-2"></i>Adjust Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div style="max-height:65vh;overflow:hidden;background:#f1f5f9;border-radius:12px;">
                    <img id="cropImage" src="#" alt="Crop image" style="display:block;max-width:100%;">
                </div>
                <small class="text-muted d-block mt-2 text-center">Drag to position • Use the zoom controls to frame your photo.</small>
                <div class="d-flex justify-content-center gap-2 mt-3">
                    <button type="button" class="btn btn-light border" id="zoomOutBtn"><i class="fas fa-search-minus"></i></button>
                    <button type="button" class="btn btn-light border" id="zoomInBtn"><i class="fas fa-search-plus"></i></button>
                    <button type="button" class="btn btn-light border" id="rotateBtn"><i class="fas fa-redo"></i> Rotate</button>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary fw-semibold" id="applyCropBtn"><i class="fas fa-check me-1"></i> Use This Crop</button>
            </div>
        </div>
    </div>
</div>

<?php if (in_array($user['role'], ['admin', 'tenant'], true)): ?>
<div class="modal fade" id="deleteMyAccountModal" tabindex="-1" aria-labelledby="deleteMyAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden;">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:52px;height:52px;background:#fef2f2;color:#dc2626;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;"><i class="fas fa-user-times"></i></div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="deleteMyAccountModalLabel">Delete My Account?</h5>
                        <small class="text-muted">Permanent account removal</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=delete_my_account" method="POST">
                <div class="modal-body px-4 pb-3">
                    <p class="text-dark mb-2">Are you sure you want to permanently delete your account?</p>
                    <p class="text-muted small">Your account will be removed and you will be signed out. <strong>This action cannot be undone.</strong></p>
                    <div class="alert alert-danger border-0 small mb-3" style="background:#fef2f2;">
                        <i class="fas fa-exclamation-triangle me-1"></i> All account access will be permanently removed.
                    </div>
                    <label class="form-label small fw-semibold">Current Password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold"><i class="fas fa-trash-alt me-1"></i> Delete My Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
function toggleProfileEditor(force) {
    const panel = document.getElementById('profileEditorPanel');
    const pictureEditor = document.getElementById('profilePictureEditor');
    const btn = document.getElementById('editProfileBtn');
    if (!panel) return;

    const show = typeof force === 'boolean' ? force : panel.style.display === 'none';
    panel.style.display = show ? 'block' : 'none';
    if (pictureEditor) pictureEditor.style.display = show ? 'block' : 'none';

    if (btn) {
        btn.innerHTML = show
            ? '<i class="fas fa-times me-1"></i> Close'
            : '<i class="fas fa-pen me-1"></i> Edit';
        btn.classList.toggle('btn-outline-secondary', show);
        btn.classList.toggle('btn-outline-primary', !show);
    }

    if (show) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

let avatarCropper = null;
const avatarForm = document.getElementById('avatarForm');
const avatarInput = document.getElementById('avatarInput');
const cropModalEl = document.getElementById('cropAvatarModal');
const cropModal = cropModalEl ? new bootstrap.Modal(cropModalEl) : null;
const cropImage = document.getElementById('cropImage');
const croppedAvatar = document.getElementById('croppedAvatar');
const editCropBtn = document.getElementById('editCropBtn');

avatarInput?.addEventListener('change', function() {
    const file = this.files?.[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { alert('Profile picture must be 2 MB or smaller.'); this.value = ''; return; }
    if (!file.type.match(/^image\/(jpeg|png|webp)$/)) { alert('Only JPG, PNG, and WEBP images are allowed.'); this.value = ''; return; }
    const reader = new FileReader();
    reader.onload = e => {
        cropImage.src = e.target.result;
        cropImage.onload = () => {
            if (avatarCropper) avatarCropper.destroy();
            avatarCropper = new Cropper(cropImage, { aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 0.9, responsive: true, background: false });
            cropModal?.show();
        };
    };
    reader.readAsDataURL(file);
});

editCropBtn?.addEventListener('click', () => cropModal?.show());
document.getElementById('zoomInBtn')?.addEventListener('click', () => avatarCropper?.zoom(0.1));
document.getElementById('zoomOutBtn')?.addEventListener('click', () => avatarCropper?.zoom(-0.1));
document.getElementById('rotateBtn')?.addEventListener('click', () => avatarCropper?.rotate(90));
document.getElementById('applyCropBtn')?.addEventListener('click', () => {
    if (!avatarCropper) return;
    avatarCropper.getCroppedCanvas({ width: 600, height: 600, imageSmoothingQuality: 'high' }).toBlob(blob => {
        const dt = new DataTransfer();
        const file = new File([blob], 'profile-cropped.jpg', { type: 'image/jpeg' });
        dt.items.add(file);
        avatarInput.files = dt.files;
        croppedAvatar.value = '1';
        document.getElementById('avatarPreview').src = URL.createObjectURL(blob);
        document.getElementById('avatarPreviewBox').style.display = 'block';
        editCropBtn.style.display = 'block';
        cropModal?.hide();
    }, 'image/jpeg', 0.9);
});

avatarForm?.addEventListener('submit', function(e) {
    if (avatarInput.files?.length && !croppedAvatar.value) {
        e.preventDefault();
        alert('Please click "Edit / Crop Photo" and choose how you want to position your picture before saving.');
        cropModal?.show();
    }
});


let otpTimer = null;
let countdown = 60;

document.getElementById('btnSendOtp').addEventListener('click', function() {
    const btn = this;
    const btnText = document.getElementById('btnSendOtpText');
    const feedback = document.getElementById('otpFeedback');

    btn.disabled = true;
    btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    feedback.style.display = 'block';
    feedback.className = 'small mt-1 text-muted';
    feedback.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending 6-digit verification code to your email...';

    fetch('<?php echo BASE_URL; ?>controllers/AuthController.php?action=send_profile_otp', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            feedback.className = 'small mt-1 text-success fw-medium';
            feedback.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
            document.getElementById('otp_code').focus();

            // Start 60-second cooldown timer
            countdown = 60;
            btnText.textContent = `Resend (${countdown}s)`;
            clearInterval(otpTimer);
            otpTimer = setInterval(() => {
                countdown--;
                if (countdown > 0) {
                    btnText.textContent = `Resend (${countdown}s)`;
                } else {
                    clearInterval(otpTimer);
                    btn.disabled = false;
                    btnText.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send OTP';
                }
            }, 1000);
        } else {
            feedback.className = 'small mt-1 text-danger fw-medium';
            feedback.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> ' + data.message;
            btn.disabled = false;
            btnText.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send OTP';
        }
    })
    .catch(err => {
        feedback.className = 'small mt-1 text-danger fw-medium';
        feedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> Could not connect to the server. Please try again.';
        btn.disabled = false;
        btnText.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send OTP';
    });
});

function validateProfileForm() {
    const currentPass = document.getElementById('current_password').value.trim();
    const otpCode = document.getElementById('otp_code').value.trim();
    const newPass = document.getElementById('new_password').value.trim();
    const confirmPass = document.getElementById('confirm_password').value.trim();

    const isChangingPassword = newPass !== '' || confirmPass !== '' || currentPass !== '' || otpCode !== '';

    if (isChangingPassword) {
        if (currentPass === '') {
            alert('Please enter your Current Password to authenticate the password change.');
            document.getElementById('current_password').focus();
            return false;
        }

        if (otpCode === '') {
            alert('Please click "Send OTP" to receive a verification code at your email, then enter the 6-digit OTP code.');
            document.getElementById('otp_code').focus();
            return false;
        }

        if (otpCode.length !== 6 || !/^\d{6}$/.test(otpCode)) {
            alert('Please enter a valid 6-digit numeric OTP verification code.');
            document.getElementById('otp_code').focus();
            return false;
        }

        if (newPass === '') {
            alert('Please enter your New Password.');
            document.getElementById('new_password').focus();
            return false;
        }

        if (newPass.length < 6) {
            alert('Your New Password must be at least 6 characters in length.');
            document.getElementById('new_password').focus();
            return false;
        }

        if (newPass !== confirmPass) {
            alert('New Password and Confirm New Password do not match. Please re-enter.');
            document.getElementById('confirm_password').focus();
            return false;
        }
    }

    return true;
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
