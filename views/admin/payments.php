<?php
/**
 * Admin: Payment Collections, Transaction Receipts, and Verification Center
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Payment Collections & Receipts';

// Fetch all payments with tenant, unit, invoice, and receiver info
$payments = $pdo->query("SELECT p.*, t.first_name, t.last_name, u.unit_number, inv.invoice_number, u_rec.name as receiver_name
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    JOIN invoices inv ON p.invoice_id = inv.id
    JOIN units u ON inv.unit_id = u.id
    LEFT JOIN users u_rec ON p.received_by = u_rec.id
    ORDER BY p.payment_date DESC, p.id DESC")->fetchAll();

// Pending payment verification counter
$pendingVerificationCount = count(array_filter($payments, fn($p) => $p['status'] === 'pending_verification'));

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Payment Collections</h1>
        <p class="page-subtitle">Verify online GCash/bank uploads, issue official receipts, and audit collection logs.</p>
    </div>
</div>

<?php if ($pendingVerificationCount > 0): ?>
    <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-bell fs-4 me-3 text-warning"></i>
            <div>
                <strong><?php echo $pendingVerificationCount; ?> Pending Payment Proof(s)</strong> require your review and verification.
            </div>
        </div>
        <span class="badge bg-warning text-dark px-3 py-2">Action Needed</span>
    </div>
<?php endif; ?>

<!-- Search & Summary Stats -->
<div class="custom-card mb-4">
    <div class="custom-card-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search payment reference, tenant, or method..." data-table-search="paymentsTable">
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <span class="badge bg-light text-dark border p-2">Total Collections: <strong><?php echo count($payments); ?></strong></span>
                <span class="badge bg-success-subtle text-success border border-success-subtle p-2"><i class="fas fa-check-double me-1"></i> Verified: <strong><?php echo count(array_filter($payments, fn($p) => $p['status'] === 'confirmed')); ?></strong></span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle p-2"><i class="fas fa-hourglass-half me-1"></i> Pending: <strong><?php echo $pendingVerificationCount; ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Payments Master Table -->
<div class="custom-card">
    <div class="table-responsive">
        <table class="table table-custom mb-0" id="paymentsTable">
            <thead>
                <tr>
                    <th>Payment Ref</th>
                    <th>Tenant / Unit</th>
                    <th>Invoice #</th>
                    <th>Amount Paid</th>
                    <th>Method / Trans No.</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Proof</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No payment collections found.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td>
                                <strong class="text-dark font-monospace"><?php echo htmlspecialchars($pay['payment_reference']); ?></strong>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($pay['unit_number']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($pay['invoice_number']); ?></span>
                            </td>
                            <td class="fw-bold text-success fs-6">
                                <?php echo formatPeso($pay['amount']); ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($pay['payment_method']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($pay['transaction_ref_no'] ?? '—'); ?></small>
                            </td>
                            <td class="text-muted small">
                                <?php echo formatDate($pay['payment_date']); ?>
                            </td>
                            <td>
                                <?php echo statusBadge($pay['status'], 'payment'); ?>
                            </td>
                            <td>
                                <?php if (!empty($pay['proof_of_payment'])): ?>
                                    <a href="<?php echo BASE_URL; ?>assets/uploads/<?php echo htmlspecialchars($pay['proof_of_payment']); ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                        <i class="fas fa-image me-1"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($pay['status'] === 'pending_verification'): ?>
                                    <button class="btn btn-sm btn-warning me-1" onclick="openVerifyModal(<?php echo $pay['id']; ?>, '<?php echo $pay['payment_reference']; ?>', '<?php echo formatPeso($pay['amount']); ?>', '<?php echo htmlspecialchars($pay['proof_of_payment'] ?? ''); ?>')">
                                        <i class="fas fa-check-circle me-1"></i> Review
                                    </button>
                                <?php endif; ?>

                                <!-- Print Receipt -->
                                <a href="<?php echo BASE_URL; ?>views/shared/print_invoice.php?id=<?php echo $pay['invoice_id']; ?>&pay_id=<?php echo $pay['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Official Receipt">
                                    <i class="fas fa-print"></i> Receipt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Review & Verify Payment -->
<div class="modal fade" id="verifyPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-check-double text-primary me-2"></i>Verify Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/PaymentController.php?action=verify" method="POST">
                <input type="hidden" name="payment_id" id="verify_payment_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Payment Reference</label>
                        <div class="fw-bold fs-6 text-dark" id="verify_pay_ref"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Payment Amount</label>
                        <div class="fw-bold fs-5 text-success" id="verify_pay_amount"></div>
                    </div>
                    <div class="mb-3" id="verify_proof_container">
                        <label class="form-label small fw-semibold text-muted">Uploaded Proof / Receipt</label>
                        <div class="border rounded p-2 text-center bg-light">
                            <img id="verify_proof_img" src="" alt="Proof" class="img-fluid rounded" style="max-height: 250px;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Verification Decision</label>
                        <select name="decision" class="form-select" required>
                            <option value="confirmed">Approve & Confirm Payment (Credits Balance)</option>
                            <option value="rejected">Reject Proof (Invalid / Unrecognized)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Submit Decision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openVerifyModal(payId, payRef, amountStr, proofFile) {
    document.getElementById('verify_payment_id').value = payId;
    document.getElementById('verify_pay_ref').textContent = payRef;
    document.getElementById('verify_pay_amount').textContent = amountStr;

    const imgContainer = document.getElementById('verify_proof_container');
    const imgEl = document.getElementById('verify_proof_img');

    if (proofFile) {
        imgContainer.style.display = 'block';
        imgEl.src = '<?php echo BASE_URL; ?>assets/uploads/' + proofFile;
    } else {
        imgContainer.style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('verifyPaymentModal')).show();
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
