<?php
/**
 * Shared: Printable Official Receipt & Statement of Account (SOA)
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}

$pdo = getDBConnection();
$invoiceId = intval($_GET['id'] ?? 0);

if ($invoiceId <= 0) {
    die("Invalid invoice requested.");
}

// Fetch invoice with tenant, unit, property, and utility reading details
$stmt = $pdo->prepare("SELECT inv.*, 
    t.first_name, t.last_name, t.phone as tenant_phone, t.email as tenant_email,
    u.unit_number, u.unit_type,
    p.name as property_name, p.address as property_address,
    ur.prev_water_reading, ur.curr_water_reading, ur.water_consumption, ur.water_rate,
    ur.prev_electric_reading, ur.curr_electric_reading, ur.electric_consumption, ur.electric_rate
FROM invoices inv
JOIN tenants t ON inv.tenant_id = t.id
JOIN units u ON inv.unit_id = u.id
JOIN properties p ON u.property_id = p.id
LEFT JOIN utility_readings ur ON inv.utility_reading_id = ur.id
WHERE inv.id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice record not found.");
}

// Fetch any payments associated with this invoice
$payStmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? AND status = 'confirmed' ORDER BY payment_date ASC");
$payStmt->execute([$invoiceId]);
$payments = $payStmt->fetchAll();

// System Settings
$companyName = getSetting('system_name', 'ResiPro Apartment Management');
$companyEmail = getSetting('company_email', 'management@resipro.ph');
$companyPhone = getSetting('company_phone', '+63 917 555 8921');
$companyAddress = getSetting('company_address', '108 Sampaguita St., Diliman, Quezon City');
$gcashName = getSetting('payment_gcash_name', 'JUAN DELA CRUZ');
$gcashNum = getSetting('payment_gcash_number', '0917-555-8921');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement of Account - <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    
    <!-- Google Font & Bootstrap -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/custom.css">
</head>
<body class="bg-light py-4">

<div class="container">
    <!-- Top Action Bar (Hidden when printed) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print" style="max-width: 800px; margin: 0 auto;">
        <a href="javascript:history.back()" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print Statement / Official Receipt
        </button>
    </div>

    <!-- Printable SOA Card Document -->
    <div class="receipt-wrapper">
        <!-- Invoice Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="fas fa-building text-primary me-2"></i><?php echo htmlspecialchars($companyName); ?></h3>
                <p class="text-muted small mb-0"><?php echo htmlspecialchars($companyAddress); ?></p>
                <p class="text-muted small mb-0"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($companyEmail); ?> &bull; <i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($companyPhone); ?></p>
            </div>
            <div class="text-end">
                <h4 class="fw-bold text-primary mb-1">STATEMENT OF ACCOUNT</h4>
                <div class="font-monospace text-dark fw-bold"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                <div class="small text-muted mt-1">Date Issued: <?php echo formatDate($invoice['created_at']); ?></div>
                <div class="mt-2"><?php echo statusBadge($invoice['status'], 'invoice'); ?></div>
            </div>
        </div>

        <!-- Bill To Information -->
        <div class="row g-3 mb-4 pb-3 border-bottom">
            <div class="col-6">
                <span class="text-uppercase text-muted small fw-bold">Billed To (Tenant):</span>
                <h5 class="fw-bold text-dark mb-1 mt-1"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></h5>
                <div class="small text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($invoice['tenant_phone']); ?></div>
                <?php if (!empty($invoice['tenant_email'])): ?>
                    <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($invoice['tenant_email']); ?></div>
                <?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <span class="text-uppercase text-muted small fw-bold">Unit / Lease Particulars:</span>
                <h5 class="fw-bold text-primary mb-1 mt-1"><?php echo htmlspecialchars($invoice['unit_number']); ?> (<?php echo htmlspecialchars($invoice['unit_type']); ?>)</h5>
                <div class="small text-dark fw-semibold"><?php echo htmlspecialchars($invoice['property_name']); ?></div>
                <div class="small text-muted">Billing Period: <strong><?php echo formatDate($invoice['billing_period_start']); ?> - <?php echo formatDate($invoice['billing_period_end']); ?></strong></div>
                <div class="small text-danger fw-bold">Due Date: <?php echo formatDate($invoice['due_date']); ?></div>
            </div>
        </div>

        <!-- Itemized Charges Table -->
        <table class="table table-bordered mb-4">
            <thead class="table-light">
                <tr>
                    <th>Item Description / Particulars</th>
                    <th class="text-center" style="width: 140px;">Consumption / Rate</th>
                    <th class="text-end" style="width: 160px;">Amount (₱)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong class="text-dark">Monthly Unit Rent</strong>
                        <div class="small text-muted">Rental fee for <?php echo date('F Y', strtotime($invoice['billing_period_start'])); ?></div>
                    </td>
                    <td class="text-center small text-muted">Fixed Monthly</td>
                    <td class="text-end fw-semibold"><?php echo formatPeso($invoice['rent_amount']); ?></td>
                </tr>

                <?php if ($invoice['water_amount'] > 0): ?>
                    <tr>
                        <td>
                            <strong class="text-dark"><i class="fas fa-tint text-info me-1"></i>Water Sub-meter Utility</strong>
                            <div class="small text-muted">
                                Prev: <?php echo number_format($invoice['prev_water_reading'] ?? 0, 2); ?> | 
                                Curr: <?php echo number_format($invoice['curr_water_reading'] ?? 0, 2); ?>
                            </div>
                        </td>
                        <td class="text-center small">
                            <?php echo number_format($invoice['water_consumption'] ?? 0, 2); ?> cu.m @ ₱<?php echo number_format($invoice['water_rate'] ?? 45, 2); ?>
                        </td>
                        <td class="text-end fw-semibold"><?php echo formatPeso($invoice['water_amount']); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($invoice['electricity_amount'] > 0): ?>
                    <tr>
                        <td>
                            <strong class="text-dark"><i class="fas fa-bolt text-warning me-1"></i>Electricity Sub-meter Utility</strong>
                            <div class="small text-muted">
                                Prev: <?php echo number_format($invoice['prev_electric_reading'] ?? 0, 2); ?> | 
                                Curr: <?php echo number_format($invoice['curr_electric_reading'] ?? 0, 2); ?>
                            </div>
                        </td>
                        <td class="text-center small">
                            <?php echo number_format($invoice['electric_consumption'] ?? 0, 2); ?> kWh @ ₱<?php echo number_format($invoice['electric_rate'] ?? 14.5, 2); ?>
                        </td>
                        <td class="text-end fw-semibold"><?php echo formatPeso($invoice['electricity_amount']); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($invoice['penalty_amount'] > 0): ?>
                    <tr class="text-danger">
                        <td>
                            <strong><i class="fas fa-exclamation-triangle me-1"></i>Late Payment Penalty Fee</strong>
                        </td>
                        <td class="text-center small">Overdue Surcharge</td>
                        <td class="text-end fw-semibold"><?php echo formatPeso($invoice['penalty_amount']); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($invoice['other_charges'] > 0): ?>
                    <tr>
                        <td>
                            <strong class="text-dark">Other Charges: <?php echo htmlspecialchars($invoice['other_charges_notes'] ?? 'Miscellaneous Fee'); ?></strong>
                        </td>
                        <td class="text-center small">Add-on</td>
                        <td class="text-end fw-semibold"><?php echo formatPeso($invoice['other_charges']); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <td colspan="2" class="text-end fw-bold">Total Amount Due:</td>
                    <td class="text-end fw-bold text-dark fs-6"><?php echo formatPeso($invoice['total_amount']); ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="text-end fw-bold text-success">Total Amount Paid:</td>
                    <td class="text-end fw-bold text-success"><?php echo formatPeso($invoice['paid_amount']); ?></td>
                </tr>
                <tr class="table-light">
                    <td colspan="2" class="text-end fw-bold text-danger fs-6">Remaining Balance Due:</td>
                    <td class="text-end fw-bold text-danger fs-5"><?php echo formatPeso($invoice['balance']); ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Payments Log (If any) -->
        <?php if (!empty($payments)): ?>
            <div class="mb-4">
                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-receipt text-success me-1"></i>Payment Acknowledgment Records</h6>
                <table class="table table-sm table-bordered small">
                    <thead class="table-light">
                        <tr>
                            <th>Receipt Ref</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Transaction / Check No.</th>
                            <th class="text-end">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="font-monospace fw-semibold"><?php echo htmlspecialchars($p['payment_reference']); ?></td>
                                <td><?php echo formatDate($p['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($p['transaction_ref_no'] ?? '—'); ?></td>
                                <td class="text-end fw-bold text-success"><?php echo formatPeso($p['amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Payment Instructions Box -->
        <div class="p-3 bg-light rounded border mb-4 small">
            <div class="fw-bold text-dark mb-1">Payment Instructions:</div>
            <div>Please send payment on or before the due date to avoid the ₱<?php echo getSetting('default_penalty_rate', '250.00'); ?> late penalty.</div>
            <div><strong>GCash:</strong> <?php echo htmlspecialchars($gcashNum); ?> (<?php echo htmlspecialchars($gcashName); ?>) &bull; <strong>Bank:</strong> <?php echo htmlspecialchars(getSetting('payment_bank_info')); ?></div>
        </div>

        <!-- Signatures -->
        <div class="row pt-4 mt-4 text-center">
            <div class="col-6">
                <div class="border-top pt-2 mx-4">
                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></div>
                    <small class="text-muted">Tenant Signature / Acknowledgment</small>
                </div>
            </div>
            <div class="col-6">
                <div class="border-top pt-2 mx-4">
                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($companyName); ?></div>
                    <small class="text-muted">Authorized Property Landlord / Manager</small>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
