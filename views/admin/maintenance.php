<?php
/**
 * Admin: Maintenance & Repair Requests Management
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Maintenance & Repairs';

// Fetch all maintenance tickets with unit and tenant details
$tickets = $pdo->query("SELECT m.*, u.unit_number, t.first_name, t.last_name, t.phone as tenant_phone
    FROM maintenance_requests m
    JOIN units u ON m.unit_id = u.id
    JOIN tenants t ON m.tenant_id = t.id
    ORDER BY FIELD(m.status, 'pending', 'in_progress', 'completed', 'cancelled'), m.created_at DESC")->fetchAll();

// Fetch occupied units for creating new ticket
$occupiedUnits = $pdo->query("SELECT t.id as tenant_id, t.first_name, t.last_name, u.id as unit_id, u.unit_number 
    FROM tenants t 
    JOIN units u ON t.unit_id = u.id 
    WHERE t.status = 'active'
    ORDER BY u.unit_number ASC")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Maintenance & Work Orders</h1>
        <p class="page-subtitle">Track repair requests, assign technicians, monitor expenses, and resolve tenant complaints.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTicketModal">
            <i class="fas fa-tools me-1"></i> New Repair Ticket
        </button>
    </div>
</div>

<!-- Search & Status Summary -->
<div class="custom-card mb-4">
    <div class="custom-card-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search ticket issue, category, or technician..." data-table-search="ticketsTable">
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <span class="badge bg-warning-subtle text-dark border border-warning-subtle p-2"><i class="fas fa-clock me-1"></i> Pending: <strong><?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'pending')); ?></strong></span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle p-2"><i class="fas fa-spinner me-1"></i> In Progress: <strong><?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'in_progress')); ?></strong></span>
                <span class="badge bg-success-subtle text-success border border-success-subtle p-2"><i class="fas fa-check-circle me-1"></i> Resolved: <strong><?php echo count(array_filter($tickets, fn($t) => $t['status'] === 'completed')); ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Maintenance Tickets Table -->
<div class="custom-card">
    <div class="table-responsive">
        <table class="table table-custom mb-0" id="ticketsTable">
            <thead>
                <tr>
                    <th>Ticket / Issue</th>
                    <th>Unit / Tenant</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Assigned To</th>
                    <th>Repair Cost</th>
                    <th>Date Reported</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No maintenance tickets reported.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($t['issue_title']); ?></div>
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 240px;"><?php echo htmlspecialchars($t['description']); ?></small>
                            </td>
                            <td>
                                <strong class="text-primary"><?php echo htmlspecialchars($t['unit_number']); ?></strong>
                                <div class="small text-muted"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['category']); ?></span>
                            </td>
                            <td>
                                <?php echo statusBadge($t['priority'], 'priority'); ?>
                            </td>
                            <td>
                                <span class="text-dark small fw-semibold"><?php echo !empty($t['assigned_to']) ? htmlspecialchars($t['assigned_to']) : '<em class="text-muted">Unassigned</em>'; ?></span>
                            </td>
                            <td class="text-dark fw-bold">
                                <?php echo $t['repair_cost'] > 0 ? formatPeso($t['repair_cost']) : '—'; ?>
                            </td>
                            <td class="text-muted small">
                                <?php echo formatDate($t['requested_date']); ?>
                            </td>
                            <td>
                                <?php echo statusBadge($t['status'], 'maintenance'); ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick="openUpdateTicketModal(<?php echo htmlspecialchars(json_encode($t)); ?>)">
                                    <i class="fas fa-edit me-1"></i> Update
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Ticket -->
<div class="modal fade" id="newTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-tools text-primary me-2"></i>Create Maintenance Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/MaintenanceController.php?action=create" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Occupied Unit & Tenant <span class="text-danger">*</span></label>
                            <select name="tenant_unit" class="form-select" required onchange="onSelectMaintUnit(this)">
                                <option value="">-- Choose Unit --</option>
                                <?php foreach ($occupiedUnits as $ou): ?>
                                    <option value="<?php echo $ou['tenant_id'] . '_' . $ou['unit_id']; ?>" data-tenant-id="<?php echo $ou['tenant_id']; ?>" data-unit-id="<?php echo $ou['unit_id']; ?>">
                                        <?php echo htmlspecialchars($ou['unit_number']); ?> — <?php echo htmlspecialchars($ou['first_name'] . ' ' . $ou['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="tenant_id" id="maint_tenant_id">
                            <input type="hidden" name="unit_id" id="maint_unit_id">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="Plumbing">Plumbing / Water Lines</option>
                                <option value="Electrical">Electrical & Lighting</option>
                                <option value="Air Conditioning">Air Conditioning (HVAC)</option>
                                <option value="Carpentry">Carpentry / Doors / Locks</option>
                                <option value="Painting">Repainting / Wall Fixture</option>
                                <option value="Pest Control">Pest Control</option>
                                <option value="Other">Other Issues</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Issue Title / Summary <span class="text-danger">*</span></label>
                            <input type="text" name="issue_title" class="form-control" placeholder="e.g. Bathroom drain clogged" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Urgency / Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low (Minor cosmetic)</option>
                                <option value="medium" selected>Medium (Standard repair)</option>
                                <option value="high">High (Affects daily living)</option>
                                <option value="emergency">Emergency (Flooding/Sparking)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Detailed Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Describe what happened, location in unit, etc." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Update Status / Work Order -->
<div class="modal fade" id="updateTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-clipboard-check text-primary me-2"></i>Update Maintenance Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/MaintenanceController.php?action=update_status" method="POST">
                <input type="hidden" name="ticket_id" id="edit_ticket_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Issue</label>
                        <div class="fw-bold fs-6 text-dark" id="edit_issue_title"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Ticket Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="pending">Pending (Awaiting Technician)</option>
                            <option value="in_progress">In Progress (Repair Underway)</option>
                            <option value="completed">Completed / Resolved</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Assigned Technician / Contractor</label>
                        <input type="text" name="assigned_to" id="edit_assigned_to" class="form-control" placeholder="e.g. Mang Berto Plumber">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Total Repair Expense (₱)</label>
                        <input type="number" step="0.01" name="repair_cost" id="edit_repair_cost" class="form-control" placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Resolution Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="2" placeholder="Describe actions taken, parts replaced..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Work Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function onSelectMaintUnit(select) {
    const opt = select.options[select.selectedIndex];
    document.getElementById('maint_tenant_id').value = opt.getAttribute('data-tenant-id') || '';
    document.getElementById('maint_unit_id').value = opt.getAttribute('data-unit-id') || '';
}

function openUpdateTicketModal(ticket) {
    document.getElementById('edit_ticket_id').value = ticket.id;
    document.getElementById('edit_issue_title').textContent = ticket.issue_title;
    document.getElementById('edit_status').value = ticket.status;
    document.getElementById('edit_assigned_to').value = ticket.assigned_to || '';
    document.getElementById('edit_repair_cost').value = ticket.repair_cost || '0.00';
    document.getElementById('edit_notes').value = ticket.notes || '';

    new bootstrap.Modal(document.getElementById('updateTicketModal')).show();
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
