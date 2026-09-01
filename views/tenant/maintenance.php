<?php
/**
 * Tenant Portal: Maintenance & Repair Requests
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Request Maintenance';
$userId = $_SESSION['user_id'];

// Fetch all active unit records for this tenant user
$tStmt = $pdo->prepare("SELECT t.id as tenant_id, t.unit_id, u.unit_number, u.unit_type 
    FROM tenants t 
    JOIN units u ON t.unit_id = u.id 
    WHERE t.user_id = ? AND t.status = 'active'
    ORDER BY u.unit_number ASC");
$tStmt->execute([$userId]);
$myUnits = $tStmt->fetchAll();

if (empty($myUnits)) {
    $fallbackStmt = $pdo->query("SELECT t.id as tenant_id, t.unit_id, u.unit_number, u.unit_type 
        FROM tenants t 
        JOIN units u ON t.unit_id = u.id 
        WHERE t.status = 'active' LIMIT 1");
    $myUnits = $fallbackStmt->fetchAll();
}

$tenantIds = array_filter(array_column($myUnits, 'tenant_id'));
$defaultTenant = !empty($myUnits) ? $myUnits[0] : ['tenant_id' => 0, 'unit_id' => 0, 'unit_number' => 'None'];

// Fetch tickets submitted across all units of this tenant
$tickets = [];
if (!empty($tenantIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($tenantIds), '?'));
    $tickStmt = $pdo->prepare("SELECT mr.*, u.unit_number 
        FROM maintenance_requests mr 
        JOIN units u ON mr.unit_id = u.id 
        WHERE mr.tenant_id IN ($inPlaceholders) 
        ORDER BY mr.created_at DESC");
    $tickStmt->execute($tenantIds);
    $tickets = $tickStmt->fetchAll();
}

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Maintenance & Repairs</h1>
        <p class="page-subtitle">Report plumbing, electrical, or structural issues inside your rented units to property management.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tenantNewTicketModal" <?php echo empty($myUnits) ? 'disabled' : ''; ?>>
            <i class="fas fa-wrench me-1"></i> File New Repair Request
        </button>
    </div>
</div>

<!-- Maintenance Requests Table -->
<div class="custom-card">
    <div class="custom-card-header">
        <h5 class="custom-card-title"><i class="fas fa-tools text-primary me-2"></i>My Reported Tickets</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-custom mb-0">
            <thead>
                <tr>
                    <th>Issue Summary</th>
                    <th>Unit</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Date Reported</th>
                    <th>Status</th>
                    <th>Assigned Tech</th>
                    <th>Resolution Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">You have not submitted any maintenance requests.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($t['issue_title']); ?></strong>
                                <div class="small text-muted text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($t['description']); ?></div>
                            </td>
                            <td><span class="badge bg-light text-primary border font-monospace"><?php echo htmlspecialchars($t['unit_number']); ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['category']); ?></span></td>
                            <td><?php echo statusBadge($t['priority'], 'priority'); ?></td>
                            <td class="small text-muted"><?php echo formatDate($t['requested_date']); ?></td>
                            <td><?php echo statusBadge($t['status'], 'maintenance'); ?></td>
                            <td class="small fw-semibold"><?php echo !empty($t['assigned_to']) ? htmlspecialchars($t['assigned_to']) : '<em class="text-muted">Assigned upon review</em>'; ?></td>
                            <td class="small text-muted"><?php echo !empty($t['notes']) ? htmlspecialchars($t['notes']) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: File Repair Request -->
<div class="modal fade" id="tenantNewTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-tools text-primary me-2"></i>Report Maintenance Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/MaintenanceController.php?action=create" method="POST">
                <input type="hidden" name="tenant_id" id="maint_tenant_id" value="<?php echo $defaultTenant['tenant_id']; ?>">
                <input type="hidden" name="unit_id" id="maint_unit_id" value="<?php echo $defaultTenant['unit_id']; ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Affected Unit Location <span class="text-danger">*</span></label>
                            <?php if (count($myUnits) > 1): ?>
                                <select class="form-select" required onchange="onMaintUnitChange(this)">
                                    <?php foreach ($myUnits as $mu): ?>
                                        <option value="<?php echo $mu['unit_id']; ?>" data-tenant-id="<?php echo $mu['tenant_id']; ?>">
                                            <?php echo htmlspecialchars($mu['unit_number']); ?> (<?php echo htmlspecialchars($mu['unit_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($defaultTenant['unit_number'] . ' (' . ($defaultTenant['unit_type'] ?? 'Studio') . ')'); ?>" readonly>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Issue Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="Plumbing">Plumbing / Water Leak / Faucet</option>
                                <option value="Electrical">Electrical / Lights / Power Outlet</option>
                                <option value="Air Conditioning">Air Conditioning (HVAC)</option>
                                <option value="Carpentry">Door / Window / Cabinet / Lock</option>
                                <option value="Painting">Wall Paint / Moisture / Tile</option>
                                <option value="Pest Control">Pest Control</option>
                                <option value="Other">Other Issue</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Urgency Level</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low (Can wait standard schedule)</option>
                                <option value="medium" selected>Medium (Standard repair)</option>
                                <option value="high">High (Affects daily living)</option>
                                <option value="emergency">Emergency (Active flooding/spark)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Short Issue Title <span class="text-danger">*</span></label>
                            <input type="text" name="issue_title" class="form-control" placeholder="e.g. Shower head leaking / Bathroom light not working" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Detailed Description of Problem <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Please describe exactly what happened and when you are usually home for maintenance staff access." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function onMaintUnitChange(select) {
    const opt = select.options[select.selectedIndex];
    const unitId = opt.value;
    const tenantId = opt.getAttribute('data-tenant-id') || '0';
    document.getElementById('maint_unit_id').value = unitId;
    document.getElementById('maint_tenant_id').value = tenantId;
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
