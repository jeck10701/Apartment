<?php
/**
 * Tenant Portal: Main Dashboard
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Tenant Portal Dashboard';
$userId = $_SESSION['user_id'];

// 1. Fetch All Active Units Rented by this Tenant
$tStmt = $pdo->prepare("SELECT t.*, u.unit_number, u.unit_type, u.monthly_rent, u.water_rate_per_unit, u.electric_rate_per_kwh, p.name as property_name, p.address as property_address
    FROM tenants t
    JOIN units u ON t.unit_id = u.id
    JOIN properties p ON u.property_id = p.id
    WHERE t.user_id = ? AND t.status = 'active'
    ORDER BY u.unit_number ASC");
$tStmt->execute([$userId]);
$tenantUnits = $tStmt->fetchAll();

// If tenant profile is not linked directly, fetch first active tenant for demo view
if (empty($tenantUnits)) {
    $demoStmt = $pdo->query("SELECT t.*, u.unit_number, u.unit_type, u.monthly_rent, u.water_rate_per_unit, u.electric_rate_per_kwh, p.name as property_name, p.address as property_address
        FROM tenants t
        JOIN units u ON t.unit_id = u.id
        JOIN properties p ON u.property_id = p.id
        WHERE t.status = 'active' LIMIT 1");
    $demoRow = $demoStmt->fetch();
    if ($demoRow) {
        $tenantUnits = [$demoRow];
    }
}

$tenant = !empty($tenantUnits) ? $tenantUnits[0] : [];
$tenantIds = !empty($tenantUnits) ? array_filter(array_column($tenantUnits, 'id')) : [];
$unitCount = count($tenantUnits);
$unitNumbers = !empty($tenantUnits) ? array_column($tenantUnits, 'unit_number') : [];
$unitDisplay = !empty($unitNumbers) ? implode(', ', $unitNumbers) : 'None';
$totalMonthlyRent = !empty($tenantUnits) ? array_sum(array_column($tenantUnits, 'monthly_rent')) : 0;

// 2. Fetch Latest Unpaid/Overdue Invoice across all tenant units
$latestInv = null;
$totalBalance = 0;
if (!empty($tenantIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($tenantIds), '?'));
    $invStmt = $pdo->prepare("SELECT inv.*, u.unit_number 
        FROM invoices inv 
        JOIN units u ON inv.unit_id = u.id 
        WHERE inv.tenant_id IN ($inPlaceholders) AND inv.status IN ('unpaid', 'partially_paid', 'overdue') 
        ORDER BY inv.due_date ASC LIMIT 1");
    $invStmt->execute($tenantIds);
    $latestInv = $invStmt->fetch();

    $balStmt = $pdo->prepare("SELECT SUM(balance) FROM invoices WHERE tenant_id IN ($inPlaceholders) AND status IN ('unpaid', 'partially_paid', 'overdue')");
    $balStmt->execute($tenantIds);
    $totalBalance = floatval($balStmt->fetchColumn() ?? 0);
}

// 3. Fetch Recent Payments
$recentPayments = [];
if (!empty($tenantIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($tenantIds), '?'));
    $payStmt = $pdo->prepare("SELECT p.*, inv.invoice_number 
        FROM payments p 
        JOIN invoices inv ON p.invoice_id = inv.id 
        WHERE p.tenant_id IN ($inPlaceholders) 
        ORDER BY p.payment_date DESC LIMIT 3");
    $payStmt->execute($tenantIds);
    $recentPayments = $payStmt->fetchAll();
}

// 4. Fetch Active Maintenance Requests
$activeTickets = [];
if (!empty($tenantIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($tenantIds), '?'));
    $tickStmt = $pdo->prepare("SELECT mr.*, u.unit_number 
        FROM maintenance_requests mr 
        JOIN units u ON mr.unit_id = u.id 
        WHERE mr.tenant_id IN ($inPlaceholders) 
        ORDER BY mr.created_at DESC LIMIT 3");
    $tickStmt->execute($tenantIds);
    $activeTickets = $tickStmt->fetchAll();
}

// Payment receiving settings
$gcashName = getSetting('payment_gcash_name', 'JUAN DELA CRUZ');
$gcashNum = getSetting('payment_gcash_number', '0917-555-8921');
$bankInfo = getSetting('payment_bank_info', 'BDO Unibank | Account: 0048-1290-3456');

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Welcome, <?php echo htmlspecialchars($tenant['first_name'] ?? 'Tenant'); ?>!</h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($tenant['property_name'] ?? 'Apartment Residence'); ?> &bull; <strong><?php echo htmlspecialchars($unitDisplay); ?></strong> <?php if ($unitCount > 1): ?><span class="badge bg-primary ms-1"><?php echo $unitCount; ?> Units Rented</span><?php endif; ?></p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="<?php echo BASE_URL; ?>views/tenant/maintenance.php" class="btn btn-outline-secondary">
            <i class="fas fa-wrench me-1"></i> Request Repair
        </a>
        <a href="<?php echo BASE_URL; ?>views/tenant/billing.php" class="btn btn-primary">
            <i class="fas fa-receipt me-1"></i> View Statement of Account
        </a>
    </div>
</div>

<!-- Tenant Summary Banner Cards -->
<div class="row g-3 mb-4">
    <!-- Balance Card -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Current Total Due</span>
                    <div class="stat-value <?php echo $totalBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo formatPeso($totalBalance); ?>
                    </div>
                    <?php if ($latestInv): ?>
                        <small class="text-danger"><i class="fas fa-clock me-1"></i>Due by <?php echo formatDate($latestInv['due_date']); ?> (<?php echo htmlspecialchars($latestInv['unit_number']); ?>)</small>
                    <?php else: ?>
                        <small class="text-success"><i class="fas fa-check-circle me-1"></i>All accounts are settled!</small>
                    <?php endif; ?>
                </div>
                <div class="stat-icon-wrapper <?php echo $totalBalance > 0 ? 'stat-icon-red' : 'stat-icon-green'; ?>">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Unit Info Card -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Unit Assignment<?php echo $unitCount > 1 ? 's (' . $unitCount . ')' : ''; ?></span>
                    <div class="stat-value text-primary" style="<?php echo $unitCount > 2 ? 'font-size: 1.15rem;' : ''; ?>">
                        <?php echo htmlspecialchars($unitDisplay); ?>
                    </div>
                    <small class="text-muted">Total Rent: <?php echo formatPeso($totalMonthlyRent); ?>/mo</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="fas fa-home"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Lease Expiry Card -->
    <div class="col-12 col-md-12 col-xl-4">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Lease Agreement</span>
                    <div class="stat-value text-dark" style="font-size: 1.25rem;"><?php echo formatDate($tenant['lease_end'] ?? ''); ?></div>
                    <small class="text-muted">Due day: <strong>Every <?php echo $tenant['rent_due_day'] ?? 5; ?>th of month</strong></small>
                </div>
                <div class="stat-icon-wrapper stat-icon-purple">
                    <i class="fas fa-file-contract"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($unitCount > 1): ?>
<!-- Multi-Unit Detail Cards -->
<div class="custom-card mb-4">
    <div class="custom-card-header bg-light">
        <h5 class="custom-card-title"><i class="fas fa-th-large text-primary me-2"></i>My Assigned Units Breakdown</h5>
    </div>
    <div class="custom-card-body p-3">
        <div class="row g-3">
            <?php foreach ($tenantUnits as $tu): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="p-3 border rounded-3 bg-white shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-door-open me-1"></i><?php echo htmlspecialchars($tu['unit_number']); ?></h6>
                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($tu['unit_type']); ?></span>
                        </div>
                        <div class="small text-muted mb-1">Monthly Rent: <strong><?php echo formatPeso($tu['monthly_rent']); ?></strong></div>
                        <div class="small text-muted mb-1">Lease: <?php echo formatDate($tu['lease_start']); ?> &ndash; <?php echo formatDate($tu['lease_end']); ?></div>
                        <div class="small text-muted">Rent Due: <strong>Every <?php echo $tu['rent_due_day']; ?>th</strong></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Current Statement & Payment Instructions -->
    <div class="col-12 col-lg-7">
        <div class="custom-card mb-4">
            <div class="custom-card-header bg-light">
                <h5 class="custom-card-title"><i class="fas fa-file-invoice text-primary me-2"></i>Current Billing Details</h5>
                <?php if ($latestInv): ?>
                    <a href="<?php echo BASE_URL; ?>views/shared/print_invoice.php?id=<?php echo $latestInv['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-print me-1"></i> Print SOA
                    </a>
                <?php endif; ?>
            </div>
            <div class="custom-card-body">
                <?php if ($latestInv): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div>
                            <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($latestInv['invoice_number']); ?></span>
                            <div class="small text-muted mt-1">Period: <?php echo formatDate($latestInv['billing_period_start']); ?> - <?php echo formatDate($latestInv['billing_period_end']); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Status:</div>
                            <?php echo statusBadge($latestInv['status'], 'invoice'); ?>
                        </div>
                    </div>

                    <!-- Breakdown Table -->
                    <div class="table-responsive mb-3">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-door-open text-muted me-2"></i>Monthly Rent</td>
                                    <td class="text-end fw-semibold"><?php echo formatPeso($latestInv['rent_amount']); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-tint text-info me-2"></i>Water Utility Consumption</td>
                                    <td class="text-end fw-semibold"><?php echo formatPeso($latestInv['water_amount']); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-bolt text-warning me-2"></i>Electricity Utility Consumption</td>
                                    <td class="text-end fw-semibold"><?php echo formatPeso($latestInv['electricity_amount']); ?></td>
                                </tr>
                                <?php if ($latestInv['penalty_amount'] > 0): ?>
                                    <tr class="text-danger">
                                        <td><i class="fas fa-exclamation-triangle text-danger me-2"></i>Late Fee Penalty</td>
                                        <td class="text-end fw-semibold"><?php echo formatPeso($latestInv['penalty_amount']); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($latestInv['other_charges'] > 0): ?>
                                    <tr>
                                        <td><i class="fas fa-plus-circle text-muted me-2"></i>Other: <?php echo htmlspecialchars($latestInv['other_charges_notes'] ?? 'Fees'); ?></td>
                                        <td class="text-end fw-semibold"><?php echo formatPeso($latestInv['other_charges']); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="border-top table-light">
                                    <td class="fw-bold">Total Current Balance Due:</td>
                                    <td class="text-end fw-bold fs-5 text-danger"><?php echo formatPeso($latestInv['balance']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end">
                        <a href="<?php echo BASE_URL; ?>views/tenant/billing.php" class="btn btn-success">
                            <i class="fas fa-upload me-1"></i> Submit GCash / Bank Payment Proof
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success fs-1 mb-2"></i>
                        <h6 class="fw-bold text-dark">No Pending Invoices!</h6>
                        <p class="text-muted small mb-0">Your rental account is fully updated and in good standing.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Official Payment Channels & GCash Info -->
    <div class="col-12 col-lg-5">
        <div class="custom-card mb-4">
            <div class="custom-card-header bg-light">
                <h5 class="custom-card-title"><i class="fas fa-qrcode text-primary me-2"></i>How to Pay</h5>
            </div>
            <div class="custom-card-body">
                <!-- GCash Box -->
                <div class="p-3 bg-primary-subtle border border-primary-subtle rounded-3 mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="fas fa-mobile-alt text-primary fs-5"></i>
                        <span class="fw-bold text-primary">GCash Express Send</span>
                    </div>
                    <div class="small text-dark">Account Name: <strong><?php echo htmlspecialchars($gcashName); ?></strong></div>
                    <div class="small text-dark">Mobile Number: <strong class="fs-6 text-primary"><?php echo htmlspecialchars($gcashNum); ?></strong></div>
                </div>

                <!-- Bank Info Box -->
                <div class="p-3 bg-light border rounded-3 mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="fas fa-university text-secondary fs-5"></i>
                        <span class="fw-bold text-dark">Bank Transfer / Deposit</span>
                    </div>
                    <div class="small text-muted"><?php echo nl2br(htmlspecialchars($bankInfo)); ?></div>
                </div>

                <div class="small text-muted">
                    <i class="fas fa-info-circle me-1 text-primary"></i> <strong>Note:</strong> After sending your payment, take a screenshot of the transaction receipt and upload it in the <strong>"My Bills & Payments"</strong> tab for verification.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
