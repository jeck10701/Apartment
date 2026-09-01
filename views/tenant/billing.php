<?php
/**
 * Tenant Portal: Statements of Account & Payment Proof Submission
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'My Billing & Payments';
$userId = $_SESSION['user_id'];

// Fetch all active tenant records for this user
$tStmt = $pdo->prepare("SELECT id, unit_id FROM tenants WHERE user_id = ? AND status = 'active'");
$tStmt->execute([$userId]);
$tenantRows = $tStmt->fetchAll();

if (empty($tenantRows)) {
    $fallbackStmt = $pdo->query("SELECT id, unit_id FROM tenants WHERE status = 'active' LIMIT 1");
    $tenantRows = $fallbackStmt->fetchAll();
}

$tenantIds = array_filter(array_column($tenantRows, 'id'));
$tenantId = !empty($tenantIds) ? $tenantIds[0] : 0;

// Fetch all invoices for all units rented by this tenant
$invoices = [];
if (!empty($tenantIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($tenantIds), '?'));
    $invStmt = $pdo->prepare("SELECT inv.*, u.unit_number 
        FROM invoices inv 
        JOIN units u ON inv.unit_id = u.id 
        WHERE inv.tenant_id IN ($inPlaceholders) 
        ORDER BY inv.due_date DESC");
    $invStmt->execute($tenantIds);
    $invoices = $invStmt->fetchAll();
}

// Fetch all payment transactions for all units of this tenant
$payments = [];
if (!empty($tenantIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($tenantIds), '?'));
    $payStmt = $pdo->prepare("SELECT p.*, inv.invoice_number, u.unit_number 
        FROM payments p 
        JOIN invoices inv ON p.invoice_id = inv.id 
        JOIN units u ON inv.unit_id = u.id 
        WHERE p.tenant_id IN ($inPlaceholders) 
        ORDER BY p.payment_date DESC");
    $payStmt->execute($tenantIds);
    $payments = $payStmt->fetchAll();
}

// Unpaid invoices for payment dropdown
$unpaidInvoices = array_filter($invoices, fn($i) => $i['balance'] > 0);

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">My Billing & Statement of Account</h1>
        <p class="page-subtitle">View past monthly invoices, check balance breakdown, and submit online payment receipts across all your rented units.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadProofModal" <?php echo empty($unpaidInvoices) ? 'disabled title="No unpaid bills"' : ''; ?>>
            <i class="fas fa-upload me-1"></i> Submit Proof of Payment
        </button>
    </div>
</div>

<!-- Invoices List -->
<div class="custom-card mb-4">
    <div class="custom-card-header">
        <h5 class="custom-card-title"><i class="fas fa-file-invoice text-primary me-2"></i>Statement of Account (Invoices)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-custom mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Unit</th>
                    <th>Billing Period</th>
                    <th>Due Date</th>
                    <th>Rent</th>
                    <th>Water</th>
                    <th>Electricity</th>
                    <th>Penalty</th>
                    <th>Total Billed</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th class="text-end">Print</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-4">No billing statements available.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td class="font-monospace fw-bold"><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                            <td><span class="badge bg-light text-primary border font-monospace"><?php echo htmlspecialchars($inv['unit_number']); ?></span></td>
                            <td class="small text-muted"><?php echo formatDate($inv['billing_period_start'], 'M d'); ?> - <?php echo formatDate($inv['billing_period_end'], 'M d, Y'); ?></td>
                            <td><span class="small <?php echo ($inv['balance'] > 0 && strtotime($inv['due_date']) < time()) ? 'text-danger fw-bold' : 'text-dark'; ?>"><?php echo formatDate($inv['due_date']); ?></span></td>
                            <td><?php echo formatPeso($inv['rent_amount']); ?></td>
                            <td class="text-muted small"><?php echo formatPeso($inv['water_amount']); ?></td>
                            <td class="text-muted small"><?php echo formatPeso($inv['electricity_amount']); ?></td>
                            <td class="text-muted small"><?php echo formatPeso($inv['penalty_amount']); ?></td>
                            <td class="fw-bold text-dark"><?php echo formatPeso($inv['total_amount']); ?></td>
                            <td>
                                <?php if ($inv['balance'] > 0): ?>
                                    <strong class="text-danger"><?php echo formatPeso($inv['balance']); ?></strong>
                                <?php else: ?>
                                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>Settled</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo statusBadge($inv['status'], 'invoice'); ?></td>
                            <td class="text-end">
                                <a href="<?php echo BASE_URL; ?>views/shared/print_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View & Print Official SOA">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Payment Submission History -->
<div class="custom-card">
    <div class="custom-card-header">
        <h5 class="custom-card-title"><i class="fas fa-history text-success me-2"></i>My Payment Transactions & Submissions</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-custom mb-0">
            <thead>
                <tr>
                    <th>Payment Reference</th>
                    <th>Invoice #</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Ref / Trans No.</th>
                    <th>Date Paid</th>
                    <th>Status</th>
                    <th class="text-end">Official Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No payment transactions recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td class="font-monospace fw-semibold"><?php echo htmlspecialchars($p['payment_reference']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($p['invoice_number']); ?></span></td>
                            <td class="fw-bold text-success"><?php echo formatPeso($p['amount']); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                            <td class="small text-muted font-monospace"><?php echo htmlspecialchars($p['transaction_ref_no'] ?? '—'); ?></td>
                            <td class="small text-muted"><?php echo formatDate($p['payment_date']); ?></td>
                            <td><?php echo statusBadge($p['status'], 'payment'); ?></td>
                            <td class="text-end">
                                <?php if ($p['status'] === 'confirmed'): ?>
                                    <a href="<?php echo BASE_URL; ?>views/shared/print_invoice.php?id=<?php echo $p['invoice_id']; ?>&pay_id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-receipt me-1"></i> Receipt
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Verification in progress</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Submit Payment Proof -->
<div class="modal fade" id="uploadProofModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-success"><i class="fas fa-upload me-2"></i>Submit Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/PaymentController.php?action=submit_proof" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="tenant_id" value="<?php echo $tenantId; ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Invoice to Settle <span class="text-danger">*</span></label>
                        <select name="invoice_id" id="proof_inv_select" class="form-select" required onchange="onSelectProofInvoice(this)">
                            <option value="">-- Choose Invoice --</option>
                            <?php foreach ($unpaidInvoices as $ui): ?>
                                <option value="<?php echo $ui['id']; ?>" data-balance="<?php echo $ui['balance']; ?>">
                                    <?php echo htmlspecialchars($ui['invoice_number']); ?> (Unit <?php echo htmlspecialchars($ui['unit_number']); ?>) &mdash; Balance: <?php echo formatPeso($ui['balance']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="proof_amount" class="form-control fs-5 fw-bold text-success" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select" required>
                            <option value="GCash">GCash Express Send</option>
                            <option value="Maya">Maya</option>
                            <option value="Bank Transfer">Bank Transfer / Online Banking</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">GCash Reference / Transaction No. <span class="text-danger">*</span></label>
                        <input type="text" name="transaction_ref_no" class="form-control font-monospace" placeholder="e.g. 1002 4892 0184" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Date of Payment <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Screenshot / Receipt Image (JPG, PNG)</label>
                        <input type="file" name="proof_file" class="form-control" accept="image/*,.pdf">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Optional Notes / Remarks</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Sent via GCash around 2:30 PM"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i> Submit Payment Proof</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function onSelectProofInvoice(select) {
    const opt = select.options[select.selectedIndex];
    const bal = opt.getAttribute('data-balance') || '0';
    document.getElementById('proof_amount').value = bal;
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
