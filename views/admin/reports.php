<?php
/**
 * Admin: Financial Reports & Income Analytics
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Financial & Occupancy Reports';

$selectedMonth = $_GET['month'] ?? date('Y-m');

// 1. Total Collections for Selected Month
$colStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'confirmed' AND DATE_FORMAT(payment_date, '%Y-%m') = ?");
$colStmt->execute([$selectedMonth]);
$monthlyCollections = floatval($colStmt->fetchColumn() ?? 0);

// 2. Breakdown from Invoices for Selected Month
$invBreakdown = $pdo->prepare("SELECT 
    SUM(rent_amount) as total_rent,
    SUM(water_amount) as total_water,
    SUM(electricity_amount) as total_elec,
    SUM(penalty_amount) as total_penalty,
    SUM(other_charges) as total_other,
    SUM(total_amount) as grand_billed,
    SUM(balance) as total_uncollected
FROM invoices 
WHERE DATE_FORMAT(billing_period_start, '%Y-%m') = ?");
$invBreakdown->execute([$selectedMonth]);
$breakdown = $invBreakdown->fetch();

// 3. Maintenance Expenses for Selected Month
$expStmt = $pdo->prepare("SELECT SUM(repair_cost) FROM maintenance_requests WHERE status = 'completed' AND DATE_FORMAT(resolved_date, '%Y-%m') = ?");
$expStmt->execute([$selectedMonth]);
$repairExpenses = floatval($expStmt->fetchColumn() ?? 0);

// Net Income
$netIncome = $monthlyCollections - $repairExpenses;

// Detailed Monthly Invoices list
$monthlyInvoices = $pdo->prepare("SELECT inv.*, t.first_name, t.last_name, u.unit_number 
    FROM invoices inv
    JOIN tenants t ON inv.tenant_id = t.id
    JOIN units u ON inv.unit_id = u.id
    WHERE DATE_FORMAT(inv.billing_period_start, '%Y-%m') = ?
    ORDER BY inv.invoice_number ASC");
$monthlyInvoices->execute([$selectedMonth]);
$invoicesList = $monthlyInvoices->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Financial & Income Reports</h1>
        <p class="page-subtitle">Monthly income statement, collection summaries, and utility recovery breakdown.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0 no-print">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($selectedMonth); ?>" onchange="this.form.submit()">
        </form>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Report Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-label">Total Invoiced</span>
            <div class="stat-value text-dark"><?php echo formatPeso($breakdown['grand_billed'] ?? 0); ?></div>
            <small class="text-muted">Total rent & utilities billed</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-label">Total Collected</span>
            <div class="stat-value text-success"><?php echo formatPeso($monthlyCollections); ?></div>
            <small class="text-success"><i class="fas fa-check-circle me-1"></i>Actual revenue collected</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-label">Maintenance Expenses</span>
            <div class="stat-value text-danger"><?php echo formatPeso($repairExpenses); ?></div>
            <small class="text-muted">Repairs & work orders</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <span class="stat-label">Net Operating Income</span>
            <div class="stat-value text-primary"><?php echo formatPeso($netIncome); ?></div>
            <small class="text-muted">Collections minus repairs</small>
        </div>
    </div>
</div>

<!-- Revenue Breakdown Box -->
<div class="custom-card mb-4">
    <div class="custom-card-header">
        <h5 class="custom-card-title"><i class="fas fa-list-alt text-primary me-2"></i>Billed Categories Breakdown (<?php echo date('F Y', strtotime($selectedMonth . '-01')); ?>)</h5>
    </div>
    <div class="custom-card-body">
        <div class="row g-3 text-center">
            <div class="col-6 col-md">
                <div class="p-3 bg-light rounded border">
                    <span class="text-muted small">Rent Revenue</span>
                    <h5 class="fw-bold text-dark mb-0 mt-1"><?php echo formatPeso($breakdown['total_rent'] ?? 0); ?></h5>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-3 bg-light rounded border">
                    <span class="text-muted small">Water Recovery</span>
                    <h5 class="fw-bold text-info mb-0 mt-1"><?php echo formatPeso($breakdown['total_water'] ?? 0); ?></h5>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-3 bg-light rounded border">
                    <span class="text-muted small">Electricity Recovery</span>
                    <h5 class="fw-bold text-warning mb-0 mt-1"><?php echo formatPeso($breakdown['total_elec'] ?? 0); ?></h5>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-3 bg-light rounded border">
                    <span class="text-muted small">Late Penalties</span>
                    <h5 class="fw-bold text-danger mb-0 mt-1"><?php echo formatPeso($breakdown['total_penalty'] ?? 0); ?></h5>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-3 bg-light rounded border">
                    <span class="text-muted small">Uncollected Dues</span>
                    <h5 class="fw-bold text-danger mb-0 mt-1"><?php echo formatPeso($breakdown['total_uncollected'] ?? 0); ?></h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Itemized Monthly Invoices Table -->
<div class="custom-card">
    <div class="custom-card-header">
        <h5 class="custom-card-title"><i class="fas fa-file-invoice text-secondary me-2"></i>Monthly Invoices Masterlist</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-custom mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Unit</th>
                    <th>Tenant Name</th>
                    <th>Rent</th>
                    <th>Utilities</th>
                    <th>Total Billed</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoicesList)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No billing records for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoicesList as $inv): ?>
                        <tr>
                            <td class="font-monospace fw-bold"><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                            <td><strong class="text-primary"><?php echo htmlspecialchars($inv['unit_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></td>
                            <td><?php echo formatPeso($inv['rent_amount']); ?></td>
                            <td><?php echo formatPeso($inv['water_amount'] + $inv['electricity_amount']); ?></td>
                            <td class="fw-bold text-dark"><?php echo formatPeso($inv['total_amount']); ?></td>
                            <td class="text-success"><?php echo formatPeso($inv['paid_amount']); ?></td>
                            <td class="text-danger fw-semibold"><?php echo formatPeso($inv['balance']); ?></td>
                            <td><?php echo statusBadge($inv['status'], 'invoice'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
