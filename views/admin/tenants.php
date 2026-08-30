<?php
/**
 * Admin: Tenants Masterlist & Lease Management
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Tenants Masterlist';

// Fetch all tenants with assigned unit information
$tenants = $pdo->query("SELECT t.*, u.unit_number, u.unit_type, u.monthly_rent, usr.username as portal_username
    FROM tenants t
    LEFT JOIN units u ON t.unit_id = u.id
    LEFT JOIN users usr ON t.user_id = usr.id
    ORDER BY t.created_at DESC")->fetchAll();

// Fetch vacant units available for assignment
$vacantUnits = $pdo->query("SELECT id, unit_number, unit_type, monthly_rent, security_deposit FROM units WHERE status = 'vacant' ORDER BY unit_number ASC")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Tenants Masterlist</h1>
        <p class="page-subtitle">Track tenant accounts, unit assignments, lease contracts, and portal logins. New tenant accounts appear here automatically.</p>
    </div>

</div>

<!-- Search & Status Summary -->
<div class="custom-card mb-4">
    <div class="custom-card-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search tenant name, room, phone, or ID..." data-table-search="tenantsTable">
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle p-2"><i class="fas fa-users me-1"></i> Registered Tenants: <strong><?php echo count($tenants); ?></strong></span>
                <span class="badge bg-light text-muted border p-2"><i class="fas fa-door-closed me-1"></i> Vacant Units: <strong><?php echo count($vacantUnits); ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Tenants Table -->
<div class="custom-card">
    <div class="table-responsive">
        <table class="table table-custom mb-0" id="tenantsTable">
            <thead>
                <tr>
                    <th>Tenant Profile</th>
                    <th>Assigned Unit</th>
                    <th>Contact Info</th>
                    <th>Lease Period</th>
                    <th>Monthly Due Day</th>
                    <th>Deposit / Advance</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tenants)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No tenant accounts have registered yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></div>
                                <div class="text-muted small">
                                    <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($t['id_type'] ?? 'ID'); ?>: <?php echo htmlspecialchars($t['id_number'] ?? '—'); ?>
                                </div>
                                <?php if (!empty($t['portal_username'])): ?>
                                    <span class="badge bg-light text-primary border mt-1" style="font-size: 0.7rem;"><i class="fas fa-key me-1"></i>Portal: <?php echo htmlspecialchars($t['portal_username']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="text-primary"><?php echo !empty($t['unit_number']) ? htmlspecialchars($t['unit_number']) : 'Not Assigned'; ?></strong>
                                <?php if (!empty($t['unit_number'])): ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($t['unit_type']); ?> &bull; <?php echo formatPeso($t['monthly_rent']); ?>/mo</div>
                                <?php else: ?>
                                    <div class="small text-warning"><i class="fas fa-clock me-1"></i>Waiting for unit assignment</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><i class="fas fa-phone-alt text-muted me-1 small"></i><?php echo htmlspecialchars($t['phone']); ?></div>
                                <?php if (!empty($t['email'])): ?>
                                    <div class="small text-muted"><i class="fas fa-envelope text-muted me-1 small"></i><?php echo htmlspecialchars($t['email']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <div><strong>Start:</strong> <?php echo formatDate($t['lease_start']); ?></div>
                                <div class="text-muted"><strong>End:</strong> <?php echo formatDate($t['lease_end']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">Every <?php echo $t['rent_due_day']; ?>th</span>
                            </td>
                            <td class="small">
                                <div>Dep: <?php echo formatPeso($t['deposit_paid']); ?></div>
                                <div class="text-muted">Adv: <?php echo formatPeso($t['advance_paid']); ?></div>
                            </td>
                                                        <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary me-1" 
                                        onclick="openEditTenantModal(<?php echo htmlspecialchars(json_encode($t)); ?>)" 
                                        title="Edit Tenant Information">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($t['status'] === 'active'): ?>
                                    <form action="<?php echo BASE_URL; ?>controllers/TenantController.php?action=move_out" method="POST" class="d-inline" onsubmit="return confirm('Process Move-Out for <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>? This will vacate Unit <?php echo $t['unit_number']; ?>.');">
                                        <input type="hidden" name="tenant_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Process Move-Out (Checkout)">
                                            <i class="fas fa-sign-out-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Edit Tenant -->
<div class="modal fade" id="editTenantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit text-primary me-2"></i>Edit Tenant Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/TenantController.php?action=edit" method="POST">
                <input type="hidden" name="tenant_id" id="edit_tenant_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assign Unit</label>
                            <select name="unit_id" id="edit_unit_id" class="form-select">
                                <option value="">-- No Unit Assigned --</option>
                                <?php foreach ($vacantUnits as $vu): ?>
                                    <option value="<?php echo $vu['id']; ?>"><?php echo htmlspecialchars($vu['unit_number']); ?> (<?php echo htmlspecialchars($vu['unit_type']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact Mobile</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">ID Type</label>
                            <input type="text" name="id_type" id="edit_id_type" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">ID Number</label>
                            <input type="text" name="id_number" id="edit_id_number" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Lease Start</label>
                            <input type="date" name="lease_start" id="edit_lease_start" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Lease End</label>
                            <input type="date" name="lease_end" id="edit_lease_end" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Due Day (1-31)</label>
                            <input type="number" name="rent_due_day" id="edit_rent_due_day" class="form-control" min="1" max="31" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Deposit Paid (₱)</label>
                            <input type="number" step="0.01" name="deposit_paid" id="edit_deposit_paid" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Advance Paid (₱)</label>
                            <input type="number" step="0.01" name="advance_paid" id="edit_advance_paid" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Emergency Contact Person</label>
                            <input type="text" name="emergency_contact_name" id="edit_emergency_contact_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" id="edit_emergency_contact_phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Notes</label>
                            <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Tenant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateRentAutoFill(select) {
    const selectedOption = select.options[select.selectedIndex];
    const rent = selectedOption.getAttribute('data-rent') || '';
    const deposit = selectedOption.getAttribute('data-deposit') || rent;
    
    document.getElementById('deposit_paid_input').value = deposit;
    document.getElementById('advance_paid_input').value = rent;
}

function openEditTenantModal(tenant) {
    document.getElementById('edit_tenant_id').value = tenant.id;
    const unitSelect = document.getElementById('edit_unit_id');
    if (tenant.unit_id) {
        const currentValue = String(tenant.unit_id);
        if (![...unitSelect.options].some(o => o.value === currentValue)) {
            const opt = document.createElement('option');
            opt.value = currentValue;
            opt.textContent = (tenant.unit_number || 'Current Unit') + ' (Current Assignment)';
            unitSelect.appendChild(opt);
        }
    }
    unitSelect.value = tenant.unit_id || '';
    document.getElementById('edit_first_name').value = tenant.first_name;
    document.getElementById('edit_last_name').value = tenant.last_name;
    document.getElementById('edit_phone').value = tenant.phone;
    document.getElementById('edit_email').value = tenant.email || '';
    document.getElementById('edit_id_type').value = tenant.id_type || '';
    document.getElementById('edit_id_number').value = tenant.id_number || '';
    document.getElementById('edit_lease_start').value = tenant.lease_start;
    document.getElementById('edit_lease_end').value = tenant.lease_end;
    document.getElementById('edit_rent_due_day').value = tenant.rent_due_day;
    document.getElementById('edit_deposit_paid').value = tenant.deposit_paid;
    document.getElementById('edit_advance_paid').value = tenant.advance_paid;
    document.getElementById('edit_emergency_contact_name').value = tenant.emergency_contact_name || '';
    document.getElementById('edit_emergency_contact_phone').value = tenant.emergency_contact_phone || '';
    document.getElementById('edit_notes').value = tenant.notes || '';
    
    new bootstrap.Modal(document.getElementById('editTenantModal')).show();
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
