<?php
/**
 * Admin: Invoices, Monthly Billing, and Statements of Account (SOA)
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Invoices & Billing';

// Fetch all invoices with tenant and unit details
$invoices = $pdo->query("SELECT inv.*, t.first_name, t.last_name, t.phone, u.unit_number, u.unit_type
    FROM invoices inv
    JOIN tenants t ON inv.tenant_id = t.id
    JOIN units u ON inv.unit_id = u.id
    ORDER BY inv.due_date DESC, inv.id DESC")->fetchAll();

// Fetch active tenants for manual invoice creation
$activeTenants = $pdo->query("SELECT t.id as tenant_id, t.first_name, t.last_name, u.unit_number, u.monthly_rent
    FROM tenants t
    JOIN units u ON t.unit_id = u.id
    WHERE t.status = 'active'
    ORDER BY u.unit_number ASC")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Invoices & Monthly Billing</h1>
        <p class="page-subtitle">Generate Statements of Account, track rent balances, and apply overdue penalties.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
            <i class="fas fa-plus-circle me-1"></i> Generate Invoice
        </button>
    </div>
</div>

<!-- Search & Summary Stats -->
<div class="custom-card mb-4">
    <div class="custom-card-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search invoice #, tenant, or unit..." data-table-search="invoicesTable">
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <span class="badge bg-light text-dark border p-2">Total Invoices: <strong><?php echo count($invoices); ?></strong></span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle p-2">Unpaid: <strong><?php echo count(array_filter($invoices, fn($i) => in_array($i['status'], ['unpaid', 'partially_paid']))); ?></strong></span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle p-2">Overdue: <strong><?php echo count(array_filter($invoices, fn($i) => $i['status'] === 'overdue')); ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Invoices Master Table -->
<div class="custom-card">
    <div class="table-responsive">
        <table class="table table-custom mb-0" id="invoicesTable">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Tenant / Unit</th>
                    <th>Billing Period</th>
                    <th>Due Date</th>
                    <th>Rent</th>
                    <th>Utilities</th>
                    <th>Total Due</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No billing invoices found.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($inv['invoice_number']); ?></strong>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></div>
                                <small class="text-primary font-monospace"><?php echo htmlspecialchars($inv['unit_number']); ?></small>
                            </td>
                            <td class="small text-muted">
                                <?php echo formatDate($inv['billing_period_start'], 'M d'); ?> - <?php echo formatDate($inv['billing_period_end'], 'M d, Y'); ?>
                            </td>
                            <td>
                                <span class="small <?php echo (strtotime($inv['due_date']) < time() && $inv['balance'] > 0) ? 'text-danger fw-bold' : 'text-dark'; ?>">
                                    <?php echo formatDate($inv['due_date']); ?>
                                </span>
                            </td>
                            <td class="text-dark"><?php echo formatPeso($inv['rent_amount']); ?></td>
                            <td class="small text-muted">
                                <div><i class="fas fa-tint text-info me-1"></i><?php echo formatPeso($inv['water_amount']); ?></div>
                                <div><i class="fas fa-bolt text-warning me-1"></i><?php echo formatPeso($inv['electricity_amount']); ?></div>
                            </td>
                            <td class="fw-bold text-dark"><?php echo formatPeso($inv['total_amount']); ?></td>
                            <td>
                                <?php if ($inv['balance'] > 0): ?>
                                    <strong class="text-danger"><?php echo formatPeso($inv['balance']); ?></strong>
                                <?php else: ?>
                                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>₱ 0.00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo statusBadge($inv['status'], 'invoice'); ?>
                            </td>
                            <td class="text-end">
                                <!-- Print SOA / Receipt -->
                                <a href="<?php echo BASE_URL; ?>views/shared/print_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="Print Invoice / Official SOA">
                                    <i class="fas fa-print"></i>
                                </a>

                                <?php if ($inv['balance'] > 0): ?>
                                    <!-- Apply Penalty Modal Trigger -->
                                    <button type="button" class="btn btn-sm btn-outline-danger me-1" onclick="openPenaltyModal(<?php echo $inv['id']; ?>, '<?php echo $inv['invoice_number']; ?>')" title="Apply Overdue Penalty">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </button>

                                    <!-- Quick Record Payment Trigger -->
                                    <button type="button" class="btn btn-sm btn-success" onclick="openPayModal(<?php echo $inv['id']; ?>, '<?php echo $inv['invoice_number']; ?>', <?php echo $inv['balance']; ?>)" title="Record Payment">
                                        <i class="fas fa-hand-holding-usd me-1"></i> Pay
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Generate Invoice -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Generate Statement of Account (SOA)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/BillingController.php?action=create" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Select Tenant & Unit <span class="text-danger">*</span></label>
                            <select name="tenant_id" id="invoice_tenant_select" class="form-select" required onchange="onSelectTenantForInvoice(this)">
                                <option value="">-- Choose Tenant --</option>
                                <?php foreach ($activeTenants as $t): ?>
                                    <option value="<?php echo $t['tenant_id']; ?>" data-rent="<?php echo $t['monthly_rent']; ?>">
                                        <?php echo htmlspecialchars($t['unit_number']); ?> — <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Payment Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-05', strtotime('+1 month')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Billing Period Start</label>
                            <input type="date" name="billing_period_start" id="invoice_period_start" class="form-control" value="<?php echo date('Y-m-01'); ?>" required onchange="loadInvoiceUtility()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Billing Period End</label>
                            <input type="date" name="billing_period_end" class="form-control" value="<?php echo date('Y-m-t'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Rent Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="rent_amount" id="inv_rent_amount" class="form-control" placeholder="0.00" required oninput="calcManualInvoiceTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Water Amount (₱)</label>
                            <small class="text-muted d-block mb-1" id="inv_water_hint">Select tenant to load sub-meter bill.</small>
                            <input type="number" step="0.01" name="water_amount" id="inv_water_amount" class="form-control bg-light" value="0.00" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Electricity Amount (₱)</label>
                            <small class="text-muted d-block mb-1" id="inv_elec_hint">Select tenant to load sub-meter bill.</small>
                            <input type="number" step="0.01" name="electricity_amount" id="inv_elec_amount" class="form-control bg-light" value="0.00" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Late Penalty (₱)</label>
                            <input type="number" step="0.01" name="penalty_amount" id="inv_penalty_amount" class="form-control" value="0.00" oninput="calcManualInvoiceTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Other Charges (₱)</label>
                            <input type="number" step="0.01" name="other_charges" id="inv_other_amount" class="form-control" value="0.00" oninput="calcManualInvoiceTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Other Charges Description</label>
                            <input type="text" name="other_charges_notes" class="form-control" placeholder="e.g. Garbage Fee">
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                        <span class="fw-semibold text-dark">Calculated Total Invoice Amount:</span>
                        <h4 class="fw-bold text-primary mb-0" id="manual_inv_total_display">₱ 0.00</h4>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> Issue Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Apply Late Penalty -->
<div class="modal fade" id="penaltyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Apply Overdue Late Fee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/BillingController.php?action=apply_penalty" method="POST">
                <input type="hidden" name="invoice_id" id="penalty_invoice_id">
                <div class="modal-body p-4">
                    <p class="text-muted small">You are applying a late penalty fee to invoice <strong class="text-dark" id="penalty_invoice_code"></strong>.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Late Penalty Amount (₱)</label>
                        <input type="number" step="0.01" name="penalty_amount" class="form-control" value="250.00" required>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-plus me-1"></i> Apply Penalty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Quick Record Payment -->
<div class="modal fade" id="quickPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-success"><i class="fas fa-hand-holding-usd me-2"></i>Record Rent Collection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/PaymentController.php?action=record" method="POST">
                <input type="hidden" name="invoice_id" id="pay_invoice_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Invoice Number</label>
                        <input type="text" id="pay_invoice_number" class="form-control bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control fs-5 fw-bold text-success" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash Collection</option>
                            <option value="GCash">GCash</option>
                            <option value="Maya">Maya</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reference / Transaction Number</label>
                        <input type="text" name="transaction_ref_no" class="form-control" placeholder="e.g. GCash Ref / OR Number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-double me-1"></i> Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetInvoiceUtilityFields() {
    document.getElementById('inv_water_amount').value = '0.00';
    document.getElementById('inv_elec_amount').value = '0.00';
    document.getElementById('inv_water_hint').textContent = 'Select tenant to load sub-meter bill.';
    document.getElementById('inv_elec_hint').textContent = 'Select tenant to load sub-meter bill.';
    calcManualInvoiceTotal();
}

function onSelectTenantForInvoice(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) {
        document.getElementById('inv_rent_amount').value = '0.00';
        resetInvoiceUtilityFields();
        return;
    }

    const rent = opt.getAttribute('data-rent') || '0';
    document.getElementById('inv_rent_amount').value = rent;
    loadInvoiceUtility();
}

function loadInvoiceUtility() {
    const select = document.getElementById('invoice_tenant_select');
    const period = document.getElementById('invoice_period_start')?.value || '';
    if (!select || !select.value || !/^\d{4}-\d{2}-\d{2}$/.test(period)) {
        resetInvoiceUtilityFields();
        return;
    }

    const billingMonth = period.substring(0, 7);
    document.getElementById('inv_water_hint').textContent = 'Loading saved sub-meter reading...';
    document.getElementById('inv_elec_hint').textContent = 'Loading saved sub-meter reading...';

    fetch('<?php echo BASE_URL; ?>controllers/BillingController.php?action=get_utility_for_invoice&tenant_id=' + encodeURIComponent(select.value) + '&billing_month=' + encodeURIComponent(billingMonth), {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            resetInvoiceUtilityFields();
            return;
        }

        document.getElementById('inv_water_amount').value = Number(data.water_amount || 0).toFixed(2);
        document.getElementById('inv_elec_amount').value = Number(data.electricity_amount || 0).toFixed(2);

        if (data.found) {
            document.getElementById('inv_water_hint').textContent = 'Loaded from saved sub-meter reading.';
            document.getElementById('inv_elec_hint').textContent = 'Loaded from saved sub-meter reading.';
            document.getElementById('inv_water_hint').className = 'text-success d-block mb-1';
            document.getElementById('inv_elec_hint').className = 'text-success d-block mb-1';
        } else {
            document.getElementById('inv_water_hint').textContent = 'No sub-meter reading saved for this month.';
            document.getElementById('inv_elec_hint').textContent = 'No sub-meter reading saved for this month.';
            document.getElementById('inv_water_hint').className = 'text-muted d-block mb-1';
            document.getElementById('inv_elec_hint').className = 'text-muted d-block mb-1';
        }
        calcManualInvoiceTotal();
    })
    .catch(() => resetInvoiceUtilityFields());
}

function calcManualInvoiceTotal() {
    const rent = parseFloat(document.getElementById('inv_rent_amount').value) || 0;
    const water = parseFloat(document.getElementById('inv_water_amount').value) || 0;
    const elec = parseFloat(document.getElementById('inv_elec_amount').value) || 0;
    const pen = parseFloat(document.getElementById('inv_penalty_amount').value) || 0;
    const other = parseFloat(document.getElementById('inv_other_amount').value) || 0;
    
    const total = rent + water + elec + pen + other;
    document.getElementById('manual_inv_total_display').textContent = '₱ ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.getElementById('createInvoiceModal')?.addEventListener('shown.bs.modal', function() {
    loadInvoiceUtility();
});

function openPenaltyModal(invoiceId, invoiceNumber) {
    document.getElementById('penalty_invoice_id').value = invoiceId;
    document.getElementById('penalty_invoice_code').textContent = invoiceNumber;
    new bootstrap.Modal(document.getElementById('penaltyModal')).show();
}

function openPayModal(invoiceId, invoiceNumber, balance) {
    document.getElementById('pay_invoice_id').value = invoiceId;
    document.getElementById('pay_invoice_number').value = invoiceNumber;
    document.getElementById('pay_amount').value = balance;
    new bootstrap.Modal(document.getElementById('quickPayModal')).show();
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
