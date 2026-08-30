<?php
/**
 * Tenant Portal: Maintenance & Repair Requests
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Request Maintenance';
$userId = $_SESSION['user_id'];

// Fetch tenant and unit record
$tStmt = $pdo->prepare("SELECT id, first_name, last_name, unit_id FROM tenants WHERE user_id = ? AND status = 'active' LIMIT 1");
$tStmt->execute([$userId]);
$tenant = $tStmt->fetch();

if (!$tenant) {
    $tenant = $pdo->query("SELECT id, first_name, last_name, unit_id FROM tenants WHERE status = 'active' LIMIT 1")->fetch();
}

$tenantId = $tenant['id'] ?? 0;
$unitId   = $tenant['unit_id'] ?? 0;

// Fetch tickets submitted by this tenant
$tickets = [];
if ($tenantId) {
    $tickStmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE tenant_id = ? ORDER BY created_at DESC");
    $tickStmt->execute([$tenantId]);
    $tickets = $tickStmt->fetchAll();
}

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Maintenance & Repairs</h1>
        <p class="page-subtitle">Report plumbing, electrical, or structural issues inside your unit to property management.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tenantNewTicketModal">
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
                    <tr><td colspan="7" class="text-center text-muted py-4">You have not submitted any maintenance requests.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($t['issue_title']); ?></strong>
                                <div class="small text-muted text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($t['description']); ?></div>
                            </td>
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
                <input type="hidden" name="tenant_id" value="<?php echo $tenantId; ?>">
                <input type="hidden" name="unit_id" value="<?php echo $unitId; ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
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
                        <div class="col-md-6">
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

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
