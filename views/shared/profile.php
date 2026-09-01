<?php
/**
 * Shared: User Profile Settings, Full Information View & Photo Crop Tool
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Account Profile & Settings';
$userId = $_SESSION['user_id'];

// 1. Fetch latest user details from DB
$uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

if (!$user) {
    setFlash('danger', 'User account not found.');
    redirect(BASE_URL . 'login.php');
}

// 2. Fetch role-specific details
$tenantUnits = [];
$adminProperties = [];
$superAdminStats = [];
$totalTenantBalance = 0;

if ($user['role'] === 'tenant') {
    $tStmt = $pdo->prepare("SELECT t.*, u.unit_number, u.unit_type, u.monthly_rent, u.water_rate_per_unit, u.electric_rate_per_kwh, 
            p.name as property_name, p.address as property_address
        FROM tenants t
        LEFT JOIN units u ON t.unit_id = u.id
        LEFT JOIN properties p ON u.property_id = p.id
        WHERE t.user_id = ?
        ORDER BY (t.status = 'active') DESC, t.created_at DESC");
    $tStmt->execute([$userId]);
    $tenantUnits = $tStmt->fetchAll();

    // Calculate outstanding balance across all units
    $tenantIds = array_filter(array_column($tenantUnits, 'id'));
    if (!empty($tenantIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($tenantIds), '?'));
        $balStmt = $pdo->prepare("SELECT SUM(balance) FROM invoices WHERE tenant_id IN ($inPlaceholders) AND status IN ('unpaid', 'partially_paid', 'overdue')");
        $balStmt->execute($tenantIds);
        $totalTenantBalance = floatval($balStmt->fetchColumn() ?? 0);
    }
} elseif ($user['role'] === 'admin') {
    $propStmt = $pdo->prepare("SELECT p.*, 
            (SELECT COUNT(*) FROM units WHERE property_id = p.id) as total_units,
            (SELECT COUNT(*) FROM units WHERE property_id = p.id AND status = 'occupied') as occupied_units,
            (SELECT COUNT(*) FROM units WHERE property_id = p.id AND status = 'vacant') as vacant_units
        FROM properties p
        WHERE p.owner_id = ?
        ORDER BY p.name ASC");
    $propStmt->execute([$userId]);
    $adminProperties = $propStmt->fetchAll();
} elseif ($user['role'] === 'super_admin') {
    $superAdminStats['total_landlords'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $superAdminStats['total_tenants'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tenant'")->fetchColumn();
    $superAdminStats['total_properties'] = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
    $superAdminStats['total_units'] = $pdo->query("SELECT COUNT(*) FROM units")->fetchColumn();
}

// Mask email for security display (e.g., je***7@gmail.com)
$userEmail = $user['email'] ?? '';
$parts = explode('@', $userEmail);
$namePart = $parts[0] ?? '';
$domainPart = $parts[1] ?? '';
$maskedEmail = (strlen($namePart) > 2) ? substr($namePart, 0, 2) . str_repeat('*', max(1, strlen($namePart) - 2)) . '@' . $domainPart : $userEmail;

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<!-- Cropper.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">

<style>
.profile-avatar-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto;
    cursor: pointer;
    display: inline-block;
}
.profile-avatar-container {
    width: 120px !important;
    height: 120px !important;
    min-width: 120px !important;
    min-height: 120px !important;
    max-width: 120px !important;
    max-height: 120px !important;
    aspect-ratio: 1 / 1 !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    margin: 0 auto;
    border: 4px solid #ffffff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
    position: relative;
    background: #e2e8f0;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.profile-avatar-img {
    width: 100% !important;
    height: 100% !important;
    min-width: 100% !important;
    min-height: 100% !important;
    aspect-ratio: 1 / 1 !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    display: block !important;
}
.profile-avatar-fallback {
    width: 100% !important;
    height: 100% !important;
    aspect-ratio: 1 / 1 !important;
    border-radius: 50% !important;
    background: linear-gradient(135deg, var(--primary-color, #2563eb), #1d4ed8);
    color: #fff;
    font-size: 2.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.avatar-badge-btn {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #2563eb;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    font-size: 0.85rem;
    transition: transform 0.2s ease, background-color 0.2s ease;
    z-index: 5;
}
.profile-avatar-wrapper:hover .profile-avatar-container {
    transform: scale(1.02);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.25);
}
.profile-avatar-wrapper:hover .avatar-badge-btn {
    background: #1d4ed8;
    transform: scale(1.1);
}
.info-field-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    height: 100%;
}
.info-field-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 4px;
}
.info-field-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
}
.nav-pills-custom .nav-link {
    font-weight: 600;
    font-size: 0.9rem;
    color: #64748b;
    border-radius: 10px;
    padding: 10px 18px;
    transition: all 0.2s ease;
}
.nav-pills-custom .nav-link.active {
    background-color: var(--primary-color, #2563eb);
    color: #fff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.crop-preview-circle {
    width: 120px !important;
    height: 120px !important;
    min-width: 120px !important;
    min-height: 120px !important;
    max-width: 120px !important;
    max-height: 120px !important;
    aspect-ratio: 1 / 1 !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    border: 3px solid #3b82f6 !important;
    margin: 0 auto !important;
    background: #0f172a !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}
.crop-preview-circle img {
    max-width: none !important;
    max-height: none !important;
}
</style>

<!-- Hidden File Input for Native File Selection directly from OS Explorer -->
<input type="file" id="avatarFileInput" accept="image/*,.jpg,.jpeg,.png,.webp,.jfif" style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;">

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">My Account Profile & Settings</h1>
        <p class="page-subtitle">View your complete account information, adjust & center your profile picture, or manage your login security.</p>
    </div>
</div>

<!-- Main Top Profile Banner -->
<div class="custom-card mb-4 overflow-hidden border-0 shadow-sm" style="border-radius: 16px;">
    <div class="p-4 bg-light border-bottom">
        <div class="row align-items-center g-4">
            <div class="col-12 col-md-auto text-center">
                <!-- Click opens File Explorer directly -->
                <div class="profile-avatar-wrapper position-relative d-inline-block" onclick="openAvatarFilePicker()" title="Click to choose a photo and center/crop">
                    <div class="profile-avatar-container mb-0">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo BASE_URL . htmlspecialchars($user['avatar']); ?>" alt="Profile Picture" id="mainAvatarDisplay" class="profile-avatar-img">
                        <?php else: ?>
                            <div class="profile-avatar-fallback" id="mainAvatarFallback">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-badge-btn" title="Upload & Crop Photo">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md text-center text-md-start">
                <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                    <h3 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($user['name']); ?></h3>
                    <?php if ($user['status'] === 'active'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill small">
                            <i class="fas fa-check-circle me-1"></i>Active Account
                        </span>
                    <?php endif; ?>
                </div>

                <div class="text-muted small mb-3">
                    <span class="font-monospace fw-semibold text-primary">@<?php echo htmlspecialchars($user['username']); ?></span>
                    &bull; 
                    <span><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?></span>
                    <?php if (!empty($user['phone'])): ?>
                        &bull; <span><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['phone']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2">
                    <?php if ($user['role'] === 'super_admin'): ?>
                        <span class="badge bg-danger text-white px-3 py-2 rounded-pill font-monospace" style="font-size: 0.8rem;">
                            <i class="fas fa-shield-alt me-1"></i> SUPER ADMINISTRATOR
                        </span>
                    <?php elseif ($user['role'] === 'admin'): ?>
                        <span class="badge bg-primary text-white px-3 py-2 rounded-pill font-monospace" style="font-size: 0.8rem;">
                            <i class="fas fa-building me-1"></i> PROPERTY LANDLORD / ADMIN
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success text-white px-3 py-2 rounded-pill font-monospace" style="font-size: 0.8rem;">
                            <i class="fas fa-home me-1"></i> TENANT RESIDENT
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill small">
                        <i class="fas fa-calendar-check me-1"></i> Member Since <?php echo formatDate($user['created_at'], 'F Y'); ?>
                    </span>
                </div>
            </div>

            <!-- Edit / Crop Profile Picture Button that directly opens OS File Explorer -->
            <div class="col-12 col-md-auto text-center text-md-end">
                <button type="button" class="btn btn-primary px-3 py-2 shadow-sm mb-0 fw-semibold" onclick="openAvatarFilePicker()">
                    <i class="fas fa-crop-alt me-1"></i> Edit / Crop Profile Picture
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs for Profile & Settings -->
    <div class="px-4 pt-3 pb-0 bg-white">
        <ul class="nav nav-pills nav-pills-custom gap-2 pb-3 border-bottom" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="pill" data-bs-target="#tab-overview" type="button" role="tab">
                    <i class="fas fa-id-card me-1"></i> Full Account Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="edit-tab" data-bs-toggle="pill" data-bs-target="#tab-edit" type="button" role="tab">
                    <i class="fas fa-user-edit me-1"></i> Edit Profile Details
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#tab-security" type="button" role="tab">
                    <i class="fas fa-lock me-1"></i> Password & Security
                </button>
            </li>
            <?php if (in_array($user['role'], ['admin', 'tenant'], true)): ?>
            <li class="nav-item ms-auto" role="presentation">
                <button class="nav-link text-danger" id="danger-tab" data-bs-toggle="pill" data-bs-target="#tab-danger" type="button" role="tab">
                    <i class="fas fa-exclamation-triangle me-1"></i> Danger Zone
                </button>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Tab Contents -->
<div class="tab-content" id="profileTabsContent">

    <!-- TAB 1: FULL ACCOUNT INFORMATION OVERVIEW -->
    <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
        
        <!-- General Personal Details Section -->
        <div class="custom-card mb-4 shadow-sm" style="border-radius: 14px;">
            <div class="custom-card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="custom-card-title mb-0"><i class="fas fa-user-check text-primary me-2"></i>Personal & Contact Information</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="switchToEditTab()">
                    <i class="fas fa-pen me-1"></i> Edit Info
                </button>
            </div>
            <div class="custom-card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="info-field-card">
                            <div class="info-field-label"><i class="fas fa-user text-muted me-1"></i> Full Legal Name</div>
                            <div class="info-field-value"><?php echo htmlspecialchars($user['name']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="info-field-card">
                            <div class="info-field-label"><i class="fas fa-at text-muted me-1"></i> Username</div>
                            <div class="info-field-value font-monospace"><?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="info-field-card">
                            <div class="info-field-label"><i class="fas fa-envelope text-muted me-1"></i> Email Address</div>
                            <div class="info-field-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="info-field-card">
                            <div class="info-field-label"><i class="fas fa-phone text-muted me-1"></i> Contact Number</div>
                            <div class="info-field-value"><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span class="text-muted fst-italic">Not provided</span>'; ?></div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="info-field-card">
                            <div class="info-field-label"><i class="fas fa-shield-alt text-muted me-1"></i> System Role</div>
                            <div class="info-field-value text-capitalize"><?php echo str_replace('_', ' ', $user['role']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="info-field-card">
                            <div class="info-field-label"><i class="fas fa-check-circle text-muted me-1"></i> Account Status</div>
                            <div class="info-field-value text-success fw-bold text-uppercase"><?php echo htmlspecialchars($user['status']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="info-field-card">
                            <div class="info-field-label"><i class="fas fa-calendar-plus text-muted me-1"></i> Account Created</div>
                            <div class="info-field-value"><?php echo formatDate($user['created_at'], 'M d, Y h:i A'); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="info-field-card">
                            <div class="info-field-label"><i class="fas fa-history text-muted me-1"></i> Last Updated</div>
                            <div class="info-field-value"><?php echo !empty($user['updated_at']) ? formatDate($user['updated_at'], 'M d, Y h:i A') : '—'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROLE SPECIFIC INFORMATION: TENANT -->
        <?php if ($user['role'] === 'tenant'): ?>
        <div class="custom-card mb-4 shadow-sm" style="border-radius: 14px;">
            <div class="custom-card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="custom-card-title mb-0"><i class="fas fa-key text-primary me-2"></i>Lease & Rented Unit Details</h5>
                <?php if (!empty($tenantUnits)): ?>
                    <span class="badge bg-primary rounded-pill"><?php echo count($tenantUnits); ?> Unit<?php echo count($tenantUnits) > 1 ? 's' : ''; ?> Assigned</span>
                <?php endif; ?>
            </div>
            <div class="custom-card-body p-4">
                <?php if (empty($tenantUnits)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-door-closed fs-1 mb-2 text-secondary"></i>
                        <h6>No active unit assigned yet</h6>
                        <p class="small mb-0">Please contact property management to assign your apartment unit and register your lease contract.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($tenantUnits as $idx => $tu): ?>
                            <div class="col-12 col-lg-6">
                                <div class="p-3 border rounded-3 bg-light h-100 position-relative">
                                    <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom">
                                        <div>
                                            <h6 class="fw-bold text-primary mb-1">
                                                <i class="fas fa-door-open me-1"></i> Unit <?php echo htmlspecialchars($tu['unit_number']); ?>
                                            </h6>
                                            <div class="small text-muted"><?php echo htmlspecialchars($tu['property_name'] ?? 'JLD Apartment Residence'); ?></div>
                                        </div>
                                        <span class="badge <?php echo $tu['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?> text-uppercase">
                                            <?php echo htmlspecialchars($tu['status']); ?> Lease
                                        </span>
                                    </div>

                                    <div class="row g-2 small">
                                        <div class="col-6">
                                            <span class="text-muted d-block">Unit Type:</span>
                                            <strong><?php echo htmlspecialchars($tu['unit_type'] ?? 'Standard'); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block">Monthly Rent:</span>
                                            <strong class="text-success"><?php echo formatPeso($tu['monthly_rent']); ?>/mo</strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block">Lease Contract:</span>
                                            <strong><?php echo formatDate($tu['lease_start']); ?> &ndash; <?php echo formatDate($tu['lease_end']); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block">Monthly Due Day:</span>
                                            <strong>Every <?php echo $tu['rent_due_day']; ?>th of the month</strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block">Security Deposit Paid:</span>
                                            <strong><?php echo formatPeso($tu['deposit_paid']); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block">Advance Rent Paid:</span>
                                            <strong><?php echo formatPeso($tu['advance_paid']); ?></strong>
                                        </div>

                                        <?php if (!empty($tu['emergency_contact_name'])): ?>
                                        <div class="col-12 mt-2 pt-2 border-top">
                                            <span class="text-muted d-block">Emergency Contact:</span>
                                            <strong><?php echo htmlspecialchars($tu['emergency_contact_name']); ?> (<?php echo htmlspecialchars($tu['emergency_contact_phone'] ?? 'N/A'); ?>)</strong>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($tu['id_type']) && !empty($tu['id_number'])): ?>
                                        <div class="col-12 mt-1">
                                            <span class="text-muted d-block">Valid ID Registered:</span>
                                            <strong><?php echo htmlspecialchars($tu['id_type']); ?>: <?php echo htmlspecialchars($tu['id_number']); ?></strong>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ROLE SPECIFIC INFORMATION: ADMIN / LANDLORD -->
        <?php if ($user['role'] === 'admin'): ?>
        <div class="custom-card mb-4 shadow-sm" style="border-radius: 14px;">
            <div class="custom-card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="custom-card-title mb-0"><i class="fas fa-building text-primary me-2"></i>Managed Properties Overview</h5>
                <a href="<?php echo BASE_URL; ?>views/admin/units.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-th-list me-1"></i> Manage Units
                </a>
            </div>
            <div class="custom-card-body p-4">
                <?php if (empty($adminProperties)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-city fs-1 mb-2 text-secondary"></i>
                        <h6>No properties assigned yet</h6>
                        <p class="small mb-0">Properties assigned to your landlord account by the Super Admin will be listed here.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($adminProperties as $p): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="p-3 border rounded-3 bg-light h-100">
                                    <h6 class="fw-bold text-primary mb-1"><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($p['name']); ?></h6>
                                    <p class="small text-muted mb-3"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($p['address'] ?? 'No address set'); ?></p>
                                    <div class="d-flex justify-content-between small border-top pt-2">
                                        <div>Total Units: <strong><?php echo $p['total_units']; ?></strong></div>
                                        <div class="text-success">Occupied: <strong><?php echo $p['occupied_units']; ?></strong></div>
                                        <div class="text-secondary">Vacant: <strong><?php echo $p['vacant_units']; ?></strong></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ROLE SPECIFIC INFORMATION: SUPER ADMIN -->
        <?php if ($user['role'] === 'super_admin'): ?>
        <div class="custom-card mb-4 shadow-sm" style="border-radius: 14px;">
            <div class="custom-card-header bg-light">
                <h5 class="custom-card-title mb-0"><i class="fas fa-server text-danger me-2"></i>Super Administrator System Scope</h5>
            </div>
            <div class="custom-card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="info-field-card text-center">
                            <div class="info-field-label">Total Landlords</div>
                            <div class="fs-4 fw-bold text-primary"><?php echo $superAdminStats['total_landlords'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="info-field-card text-center">
                            <div class="info-field-label">Total Tenants</div>
                            <div class="fs-4 fw-bold text-success"><?php echo $superAdminStats['total_tenants'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="info-field-card text-center">
                            <div class="info-field-label">Properties Registered</div>
                            <div class="fs-4 fw-bold text-dark"><?php echo $superAdminStats['total_properties'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="info-field-card text-center">
                            <div class="info-field-label">Total Rental Units</div>
                            <div class="fs-4 fw-bold text-info"><?php echo $superAdminStats['total_units'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- TAB 2: EDIT PROFILE DETAILS -->
    <div class="tab-pane fade" id="tab-edit" role="tabpanel">
        <div class="custom-card shadow-sm" style="border-radius: 14px;">
            <div class="custom-card-header bg-light">
                <h5 class="custom-card-title mb-0"><i class="fas fa-user-edit text-primary me-2"></i>Update Personal Details</h5>
            </div>
            <div class="custom-card-body p-4">
                <!-- Profile Picture Quick Selector -->
                <div class="d-flex flex-wrap align-items-center justify-content-between p-3 mb-4 rounded-3 border bg-light gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo BASE_URL . htmlspecialchars($user['avatar']); ?>" alt="Profile" class="rounded-circle border" style="width:48px;height:48px;object-fit:cover;">
                        <?php else: ?>
                            <div class="user-avatar-badge" style="width:48px;height:48px;font-size:1.1rem;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold text-dark small">Profile Photo / Avatar</div>
                            <small class="text-muted">JPG, PNG, or WEBP (Max 5MB). Click to select from file explorer and adjust crop.</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary fw-semibold" onclick="openAvatarFilePicker()">
                        <i class="fas fa-folder-open me-1"></i> Browse & Crop Photo
                    </button>
                </div>

                <form action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=update_profile" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username <span class="text-muted">(Login ID)</span></label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <small class="text-muted">Used for security OTP verification and billing notices.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Mobile Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="09171234567" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        </div>
                    </div>

                    <div class="text-end border-top pt-3 mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 3: PASSWORD & SECURITY -->
    <div class="tab-pane fade" id="tab-security" role="tabpanel">
        <div class="custom-card shadow-sm" style="border-radius: 14px;">
            <div class="custom-card-header bg-light">
                <h5 class="custom-card-title mb-0"><i class="fas fa-shield-alt text-primary me-2"></i>Change Password & Security Authentication</h5>
            </div>
            <div class="custom-card-body p-4">
                <form id="securityPasswordForm" action="<?php echo BASE_URL; ?>controllers/AuthController.php?action=update_profile" method="POST" onsubmit="return validateSecurityForm()">
                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">

                    <div class="alert alert-info py-3 px-3 small border-0 d-flex align-items-start gap-2 mb-4" style="border-radius: 10px;">
                        <i class="fas fa-info-circle text-info fs-5 mt-1"></i>
                        <div>
                            To change your password, enter your <strong>Current Password</strong>, click <strong>"Send OTP"</strong> to receive an authentication code at <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>, and enter the code below.
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="current_password" id="sec_current_password" class="form-control" placeholder="••••••••" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleSecPassword('sec_current_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Verification Code (OTP) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="otp_code" id="sec_otp_code" class="form-control font-monospace fw-bold text-center" placeholder="••••••" maxlength="6" pattern="[0-9]{6}" required>
                                <button class="btn btn-outline-primary fw-semibold" type="button" id="btnSendOtp">
                                    <i class="fas fa-paper-plane me-1"></i> <span id="btnSendOtpText">Send OTP</span>
                                </button>
                            </div>
                            <div id="otpFeedback" class="small mt-1" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="sec_new_password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required onkeyup="checkSecPasswordStrength(this.value)">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleSecPassword('sec_new_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>

                            <!-- Password Strength Meter -->
                            <div class="mt-2" id="secStrengthMeter" style="display:none;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-muted" style="font-size:0.75rem;">Password Strength:</span>
                                    <span id="secStrengthLabel" class="badge bg-secondary" style="font-size:0.7rem;">None</span>
                                </div>
                                <div class="progress" style="height: 6px; border-radius: 3px;">
                                    <div id="secStrengthProgressBar" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="sec_confirm_password" class="form-control" placeholder="Repeat new password" minlength="6" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleSecPassword('sec_confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="text-end border-top pt-3">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-key me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 4: DANGER ZONE (DELETE ACCOUNT) -->
    <?php if (in_array($user['role'], ['admin', 'tenant'], true)): ?>
    <div class="tab-pane fade" id="tab-danger" role="tabpanel">
        <div class="custom-card border-danger shadow-sm" style="border-radius: 14px;">
            <div class="custom-card-header bg-danger text-white">
                <h5 class="custom-card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Delete User Account</h5>
            </div>
            <div class="custom-card-body p-4">
                <div class="row align-items-center">
                    <div class="col-12 col-md-8">
                        <h6 class="fw-bold text-danger mb-1">Permanently Remove Your Account</h6>
                        <p class="text-muted small mb-0">Once you delete your account, your profile and login access will be permanently removed. This action cannot be undone.</p>
                    </div>
                    <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMyAccountModal">
                            <i class="fas fa-trash-alt me-1"></i> Delete My Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ========================================== -->
<!-- MODAL: INTERACTIVE PROFILE PICTURE CROPPER -->
<!-- ========================================== -->
<div class="modal fade" id="cropAvatarModal" tabindex="-1" aria-labelledby="cropAvatarModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold text-dark" id="cropAvatarModalLabel">
                    <i class="fas fa-crop-alt text-primary me-2"></i>Adjust & Center Profile Picture
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="small text-muted"><i class="fas fa-info-circle me-1 text-primary"></i> Drag image to center &bull; Scroll to zoom</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm mb-0 fw-semibold" onclick="openAvatarFilePicker()">
                        <i class="fas fa-folder-open me-1"></i> Choose Another Image
                    </button>
                </div>

                <div class="row g-4 align-items-center">
                    <!-- Cropper Canvas Viewport -->
                    <div class="col-12 col-md-8">
                        <div style="width: 100%; height: 350px; background: #0f172a; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative;">
                            <img id="cropperImageSource" src="<?php echo !empty($user['avatar']) ? BASE_URL . htmlspecialchars($user['avatar']) : BASE_URL . 'assets/images/default-avatar.png'; ?>" alt="Crop Source" style="max-width: 100%; display: block;">
                        </div>
                    </div>

                    <!-- Live Circle Preview & Quick Controls -->
                    <div class="col-12 col-md-4 text-center">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="small fw-semibold text-muted text-uppercase d-block mb-2">Live Avatar Preview</span>
                            <div class="crop-preview-circle shadow-sm" id="cropCirclePreview"></div>
                            <small class="text-muted d-block mt-2" style="font-size: 0.75rem;">This circle shows how your profile photo appears to others.</small>

                            <!-- Tool buttons -->
                            <div class="d-flex flex-wrap justify-content-center gap-1 mt-3">
                                <button type="button" class="btn btn-light border btn-sm" id="btnZoomIn" title="Zoom In">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                                <button type="button" class="btn btn-light border btn-sm" id="btnZoomOut" title="Zoom Out">
                                    <i class="fas fa-search-minus"></i>
                                </button>
                                <button type="button" class="btn btn-light border btn-sm" id="btnRotateLeft" title="Rotate Left 90°">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button type="button" class="btn btn-light border btn-sm" id="btnRotateRight" title="Rotate Right 90°">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <button type="button" class="btn btn-light border btn-sm" id="btnResetCrop" title="Reset to Center">
                                    <i class="fas fa-crosshairs"></i> Center
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="cropAlertFeedback" class="alert small mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btnSaveCroppedAvatar">
                    <i class="fas fa-check-circle me-1"></i> Apply & Save Picture
                </button>
            </div>
        </div>
    </div>
</div>


<!-- MODAL: DELETE ACCOUNT -->
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

<!-- Cropper.js JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

<script>
// Switch to Edit Tab programmatically
function switchToEditTab() {
    const triggerEl = document.getElementById('edit-tab');
    if (triggerEl) {
        bootstrap.Tab.getInstance(triggerEl) ? bootstrap.Tab.getInstance(triggerEl).show() : new bootstrap.Tab(triggerEl).show();
    }
}

// Password Visibility Toggle
function toggleSecPassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}

// Password Strength Meter
function checkSecPasswordStrength(password) {
    const meter = document.getElementById('secStrengthMeter');
    const label = document.getElementById('secStrengthLabel');
    const bar = document.getElementById('secStrengthProgressBar');

    if (!password) {
        meter.style.display = 'none';
        return;
    }
    meter.style.display = 'block';

    let score = 0;
    if (password.length >= 6) score++;
    if (password.length >= 10) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score <= 1) {
        bar.style.width = '25%';
        bar.className = 'progress-bar bg-danger';
        label.className = 'badge bg-danger';
        label.textContent = 'Weak';
    } else if (score <= 2) {
        bar.style.width = '50%';
        bar.className = 'progress-bar bg-warning';
        label.className = 'badge bg-warning text-dark';
        label.textContent = 'Fair';
    } else if (score <= 4) {
        bar.style.width = '75%';
        bar.className = 'progress-bar bg-info';
        label.className = 'badge bg-info text-dark';
        label.textContent = 'Good';
    } else {
        bar.style.width = '100%';
        bar.className = 'progress-bar bg-success';
        label.className = 'badge bg-success';
        label.textContent = 'Strong';
    }
}

// ==========================================
// ==========================================
// CROPPER.JS PROFILE PICTURE LOGIC
// ==========================================
let cropperInstance = null;
const cropModalEl = document.getElementById('cropAvatarModal');
const cropperImageSource = document.getElementById('cropperImageSource');
const avatarFileInput = document.getElementById('avatarFileInput');
const btnSaveCroppedAvatar = document.getElementById('btnSaveCroppedAvatar');
const cropAlertFeedback = document.getElementById('cropAlertFeedback');

// Trigger native OS File Explorer dialog
function openAvatarFilePicker() {
    const input = document.getElementById('avatarFileInput');
    if (input) {
        input.value = ''; // Always clear previous selection so selecting the same file triggers change
        input.click();
    }
}

function showCropModal() {
    if (!cropModalEl) return;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(cropModalEl) || new bootstrap.Modal(cropModalEl);
        modal.show();
    } else {
        cropModalEl.classList.add('show');
        cropModalEl.style.display = 'block';
        document.body.classList.add('modal-open');
    }
}

function hideCropModal() {
    if (!cropModalEl) return;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(cropModalEl);
        if (modal) modal.hide();
    } else {
        cropModalEl.classList.remove('show');
        cropModalEl.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

function initCropper() {
    if (typeof Cropper === 'undefined') {
        console.warn('Cropper.js not loaded, skipping cropper init.');
        return;
    }
    if (cropperInstance) {
        cropperInstance.destroy();
        cropperInstance = null;
    }
    cropperInstance = new Cropper(cropperImageSource, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.95,
        restore: false,
        guides: true,
        center: true,
        highlight: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
        preview: '#cropCirclePreview'
    });
}

// When a file is chosen from OS File Explorer, show immediate preview in profile circle & pop up modal
avatarFileInput.addEventListener('change', function() {
    const file = this.files?.[0];
    if (!file) return;

    if (file.size > 15 * 1024 * 1024) {
        alert('Please choose an image file smaller than 15 MB.');
        this.value = '';
        return;
    }

    const fileName = (file.name || '').toLowerCase();
    const fileType = (file.type || '').toLowerCase();
    const isImage = fileType.startsWith('image/') || /\.(jpg|jpeg|png|webp|jfif)$/i.test(fileName);

    if (!isImage) {
        alert('Only JPG, JPEG, PNG, and WEBP image files are allowed.');
        this.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const imageDataUrl = e.target.result;
        cropAlertFeedback.style.display = 'none';

        // 1. Instant pop-up update in the main circle avatar on the page
        const mainImg = document.getElementById('mainAvatarDisplay');
        const fallback = document.getElementById('mainAvatarFallback');
        if (mainImg) {
            mainImg.src = imageDataUrl;
        } else if (fallback) {
            fallback.outerHTML = '<img src="' + imageDataUrl + '" alt="Profile Picture" id="mainAvatarDisplay" class="profile-avatar-img">';
        }

        // 2. Set cropper modal image source
        if (cropperInstance) {
            cropperInstance.destroy();
            cropperInstance = null;
        }
        cropperImageSource.src = imageDataUrl;

        // 3. Pop up the Cropper Modal so user can center and crop
        showCropModal();

        // 4. Initialize cropper once image is ready
        cropperImageSource.onload = function() {
            setTimeout(initCropper, 150);
        };
        setTimeout(initCropper, 200);
    };
    reader.onerror = function() {
        alert('Failed to read image file from disk.');
    };
    reader.readAsDataURL(file);
});

if (cropModalEl) {
    cropModalEl.addEventListener('shown.bs.modal', function() {
        initCropper();
    });

    cropModalEl.addEventListener('hidden.bs.modal', function() {
        if (cropperInstance) {
            cropperInstance.destroy();
            cropperInstance = null;
        }
        const input = document.getElementById('avatarFileInput');
        if (input) input.value = '';
    });
}

// Controls
document.getElementById('btnZoomIn')?.addEventListener('click', () => cropperInstance?.zoom(0.1));
document.getElementById('btnZoomOut')?.addEventListener('click', () => cropperInstance?.zoom(-0.1));
document.getElementById('btnRotateLeft')?.addEventListener('click', () => cropperInstance?.rotate(-90));
document.getElementById('btnRotateRight')?.addEventListener('click', () => cropperInstance?.rotate(90));
document.getElementById('btnResetCrop')?.addEventListener('click', () => cropperInstance?.reset());

// Save Cropped Avatar via AJAX
btnSaveCroppedAvatar.addEventListener('click', function() {
    const originalBtnHtml = btnSaveCroppedAvatar.innerHTML;
    btnSaveCroppedAvatar.disabled = true;
    btnSaveCroppedAvatar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    cropAlertFeedback.style.display = 'none';

    let base64Image = '';

    if (cropperInstance) {
        const canvas = cropperInstance.getCroppedCanvas({
            width: 600,
            height: 600,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });

        if (canvas) {
            base64Image = canvas.toDataURL('image/jpeg', 0.92);
        }
    }

    // If cropper canvas wasn't available, fallback to image source
    if (!base64Image) {
        base64Image = cropperImageSource.src;
    }

    if (!base64Image) {
        alert('Could not process the image.');
        btnSaveCroppedAvatar.disabled = false;
        btnSaveCroppedAvatar.innerHTML = originalBtnHtml;
        return;
    }

    const formData = new FormData();
    formData.append('is_ajax', '1');
    formData.append('cropped_image_data', base64Image);

    fetch('<?php echo BASE_URL; ?>controllers/AuthController.php?action=upload_avatar', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btnSaveCroppedAvatar.disabled = false;
        btnSaveCroppedAvatar.innerHTML = originalBtnHtml;

        if (data.success) {
            cropAlertFeedback.className = 'alert alert-success small mt-3';
            cropAlertFeedback.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
            cropAlertFeedback.style.display = 'block';

            // Update main avatar images on page
            const mainImg = document.getElementById('mainAvatarDisplay');
            const fallback = document.getElementById('mainAvatarFallback');
            if (mainImg) {
                mainImg.src = data.avatar_url + '?t=' + new Date().getTime();
            } else if (fallback) {
                fallback.outerHTML = '<img src="' + data.avatar_url + '?t=' + new Date().getTime() + '" alt="Profile Picture" id="mainAvatarDisplay" class="profile-avatar-img">';
            }

            // Also update navbar avatar if present
            const navAvatar = document.querySelector('.top-navbar .rounded-circle');
            if (navAvatar) {
                navAvatar.src = data.avatar_url + '?t=' + new Date().getTime();
            }

            // Also update sidebar avatar if present
            const sideAvatar = document.querySelector('.sidebar-footer .rounded-circle');
            if (sideAvatar) {
                sideAvatar.src = data.avatar_url + '?t=' + new Date().getTime();
            }

            setTimeout(() => {
                hideCropModal();
            }, 600);
        } else {
            cropAlertFeedback.className = 'alert alert-danger small mt-3';
            cropAlertFeedback.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> ' + data.message;
            cropAlertFeedback.style.display = 'block';
        }
    })
    .catch(err => {
        btnSaveCroppedAvatar.disabled = false;
        btnSaveCroppedAvatar.innerHTML = originalBtnHtml;
        cropAlertFeedback.className = 'alert alert-danger small mt-3';
        cropAlertFeedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> Network error. Please try again.';
        cropAlertFeedback.style.display = 'block';
    });
});

// ==========================================
// OTP SENDER & SECURITY FORM VALIDATION
// ==========================================
let otpTimer = null;
let countdown = 60;

document.getElementById('btnSendOtp')?.addEventListener('click', function() {
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
            document.getElementById('sec_otp_code')?.focus();

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

function validateSecurityForm() {
    const currentPass = document.getElementById('sec_current_password').value.trim();
    const otpCode = document.getElementById('sec_otp_code').value.trim();
    const newPass = document.getElementById('sec_new_password').value.trim();
    const confirmPass = document.getElementById('sec_confirm_password').value.trim();

    if (currentPass === '') {
        alert('Please enter your Current Password to authenticate.');
        document.getElementById('sec_current_password').focus();
        return false;
    }

    if (otpCode === '') {
        alert('Please click "Send OTP" and enter the 6-digit verification code sent to your email.');
        document.getElementById('sec_otp_code').focus();
        return false;
    }

    if (otpCode.length !== 6 || !/^\d{6}$/.test(otpCode)) {
        alert('Please enter a valid 6-digit numeric OTP verification code.');
        document.getElementById('sec_otp_code').focus();
        return false;
    }

    if (newPass === '') {
        alert('Please enter your New Password.');
        document.getElementById('sec_new_password').focus();
        return false;
    }

    if (newPass.length < 6) {
        alert('Your New Password must be at least 6 characters in length.');
        document.getElementById('sec_new_password').focus();
        return false;
    }

    if (newPass !== confirmPass) {
        alert('New Password and Confirm New Password do not match. Please re-enter.');
        document.getElementById('sec_confirm_password').focus();
        return false;
    }

    return true;
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>

