<?php
/**
 * Super Admin: User Accounts Management
 * Admin/Property Owner and Tenant accounts can be permanently deleted.
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'User Accounts';

$owners = $pdo->query("SELECT u.*, p.name as property_name, p.address as property_address,
    (SELECT COUNT(*) FROM units WHERE property_id = p.id) as unit_count,
    (SELECT COUNT(*) FROM tenants t JOIN units un ON t.unit_id = un.id WHERE un.property_id = p.id AND t.status = 'active') as tenant_count
    FROM users u
    LEFT JOIN properties p ON u.id = p.owner_id
    WHERE u.role = 'admin'
    ORDER BY u.created_at DESC")->fetchAll();

$tenants = $pdo->query("SELECT u.*, 
    (SELECT CONCAT(t.first_name, ' ', t.last_name)
       FROM tenants t
      WHERE t.user_id = u.id
      ORDER BY t.id DESC LIMIT 1) AS tenant_profile_name,
    (SELECT t.status
       FROM tenants t
      WHERE t.user_id = u.id
      ORDER BY t.id DESC LIMIT 1) AS tenant_status,
    (SELECT un.unit_number
       FROM tenants t
       LEFT JOIN units un ON t.unit_id = un.id
      WHERE t.user_id = u.id
      ORDER BY t.id DESC LIMIT 1) AS unit_number
    FROM users u
    WHERE u.role = 'tenant'
    ORDER BY u.created_at DESC")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">User Accounts</h1>
        <p class="page-subtitle">Manage Property Owner and Tenant accounts. Account deletion is permanent.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOwnerModal">
            <i class="fas fa-plus-circle me-1"></i> Register New Owner
        </button>
    </div>
</div>

<!-- Property Owners / Admin Accounts -->
<div class="custom-card mb-4">
    <div class="p-3 p-md-4 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h5 class="custom-card-title mb-1"><i class="fas fa-user-tie text-primary me-2"></i>Property Owner / Admin Accounts</h5>
            <p class="text-muted small mb-0">Landlord accounts registered in ResiPro.</p>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
            <?php echo count($owners); ?> account<?php echo count($owners) === 1 ? '' : 's'; ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom mb-0">
            <thead>
                <tr>
                    <th>Owner / Contact</th>
                    <th>Username</th>
                    <th>Property Name</th>
                    <th>Units Managed</th>
                    <th>Active Tenants</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($owners)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No property owner accounts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($owners as $ow): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($ow['name']); ?></div>
                                <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($ow['email']); ?></div>
                                <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($ow['phone'] ?? '—'); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($ow['username']); ?></span></td>
                            <td>
                                <div class="fw-semibold text-primary"><?php echo htmlspecialchars($ow['property_name'] ?? '—'); ?></div>
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;"><?php echo htmlspecialchars($ow['property_address'] ?? ''); ?></small>
                            </td>
                            <td><span class="fw-bold text-dark"><?php echo intval($ow['unit_count']); ?></span></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo intval($ow['tenant_count']); ?> active</span></td>
                            <td class="text-muted small"><?php echo formatDate($ow['created_at']); ?></td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="confirmDeleteAccount(<?php echo intval($ow['id']); ?>, <?php echo htmlspecialchars(json_encode($ow['name']), ENT_QUOTES, 'UTF-8'); ?>, 'Property Owner')"
                                        title="Delete Account">
                                    <i class="fas fa-trash-alt"></i> Delete Account
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tenant Accounts -->
<div class="custom-card mb-4">
    <div class="p-3 p-md-4 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h5 class="custom-card-title mb-1"><i class="fas fa-users text-primary me-2"></i>Tenant Accounts</h5>
            <p class="text-muted small mb-0">Tenant login accounts registered in the system.</p>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
            <?php echo count($tenants); ?> account<?php echo count($tenants) === 1 ? '' : 's'; ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom mb-0">
            <thead>
                <tr>
                    <th>Tenant / Contact</th>
                    <th>Username</th>
                    <th>Unit</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tenants)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No tenant accounts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($tenants as $tn): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($tn['tenant_profile_name'] ?: $tn['name']); ?></div>
                                <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($tn['email']); ?></div>
                                <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($tn['phone'] ?? '—'); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($tn['username']); ?></span></td>
                            <td><span class="fw-semibold"><?php echo htmlspecialchars($tn['unit_number'] ?? '—'); ?></span></td>
                            <td class="text-muted small"><?php echo formatDate($tn['created_at']); ?></td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="confirmDeleteAccount(<?php echo intval($tn['id']); ?>, <?php echo htmlspecialchars(json_encode($tn['name']), ENT_QUOTES, 'UTF-8'); ?>, 'Tenant')"
                                        title="Delete Account">
                                    <i class="fas fa-trash-alt"></i> Delete Account
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Account Confirmation Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:52px;height:52px;background:#fef2f2;color:#dc2626;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="deleteAccountModalLabel">Delete Account?</h5>
                        <small class="text-muted">Permanent account removal</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body px-4 pb-3">
                <p class="text-dark mb-2">
                    Are you sure you want to permanently delete this account?
                </p>
                <p class="text-muted small mb-3">
                    The account will no longer be able to sign in. This action cannot be undone.
                </p>

                <div class="p-3 rounded-3 border" style="background:#f8fafc;">
                    <div class="small text-muted mb-1">Account</div>
                    <div class="fw-bold text-dark" id="deleteAccountName">—</div>
                    <div class="small text-muted" id="deleteAccountRole">—</div>
                </div>

                <div class="alert alert-danger border-0 mt-3 mb-0 py-2 px-3 small d-flex align-items-start gap-2" style="background:#fef2f2;">
                    <i class="fas fa-exclamation-triangle mt-1"></i>
                    <span><strong>Warning:</strong> This deletion is permanent and cannot be reversed.</span>
                </div>
            </div>

            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                <form action="<?php echo BASE_URL; ?>controllers/AdminController.php?action=delete_account" method="POST" class="m-0">
                    <input type="hidden" name="account_id" id="deleteAccountId">
                    <button type="submit" class="btn btn-danger px-4 fw-semibold">
                        <i class="fas fa-trash-alt me-1"></i> Delete Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Property Owner -->
<div class="modal fade" id="addOwnerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-tie text-primary me-2"></i>Register Property Owner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/AdminController.php?action=add_owner" method="POST">
                <div class="modal-body p-4">
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Owner Profile</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Engr. Juan Dela Cruz" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="owner@email.com" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="juan.delacruz" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Mobile Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="0917-XXX-XXXX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Temporary Password</label>
                            <input type="password" name="password" class="form-control" value="admin123" required>
                        </div>
                    </div>
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Primary Property / Building</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Apartment / Building Name <span class="text-danger">*</span></label>
                            <input type="text" name="property_name" class="form-control" placeholder="e.g. Casa Mabini Residences" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Property Address <span class="text-danger">*</span></label>
                            <input type="text" name="property_address" class="form-control" placeholder="Street, Barangay, City" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Create Owner Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDeleteAccount(id, name, role) {
    document.getElementById('deleteAccountId').value = id;
    document.getElementById('deleteAccountName').textContent = name;
    document.getElementById('deleteAccountRole').textContent = role;

    const modalEl = document.getElementById('deleteAccountModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
