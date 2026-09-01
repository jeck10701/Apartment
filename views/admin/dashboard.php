<?php
/**
 * Admin / Property Owner Dashboard
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Landlord Dashboard';
$enableDashboardCharts = true;

// 1. Calculate KPI Metrics
// Total Units & Occupancy Breakdown
$unitsStmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
    SUM(CASE WHEN status = 'vacant' THEN 1 ELSE 0 END) as vacant,
    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved
FROM units");
$unitStats = $unitsStmt->fetch();

$totalUnits = intval($unitStats['total'] ?? 0);
$occupiedUnits = intval($unitStats['occupied'] ?? 0);
$vacantUnits = intval($unitStats['vacant'] ?? 0);
$maintenanceUnits = intval($unitStats['maintenance'] ?? 0);
$reservedUnits = intval($unitStats['reserved'] ?? 0);
$occupancyRate = ($totalUnits > 0) ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0;

// Monthly Collections this Month
$currentMonth = date('Y-m');
$monthlyColStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'confirmed' AND DATE_FORMAT(payment_date, '%Y-%m') = ?");
$monthlyColStmt->execute([$currentMonth]);
$monthlyCollection = floatval($monthlyColStmt->fetchColumn() ?? 0);

// Total Outstanding Receivables (Unpaid & Overdue)
$receivablesStmt = $pdo->query("SELECT SUM(balance) FROM invoices WHERE status IN ('unpaid', 'partially_paid', 'overdue')");
$totalReceivables = floatval($receivablesStmt->fetchColumn() ?? 0);

// Pending Maintenance Tickets
$pendingMaintStmt = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('pending', 'in_progress')");
$pendingMaintenance = intval($pendingMaintStmt->fetchColumn() ?? 0);

// 2. Fetch Recent Payments (Last 5)
$recentPayments = $pdo->query("SELECT p.*, t.first_name, t.last_name, u.unit_number 
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    JOIN invoices inv ON p.invoice_id = inv.id
    JOIN units u ON inv.unit_id = u.id
    ORDER BY p.created_at DESC LIMIT 5")->fetchAll();

// 3. Fetch Overdue / Unpaid Invoices (Top 5)
$urgentInvoices = $pdo->query("SELECT inv.*, t.first_name, t.last_name, t.phone, u.unit_number 
    FROM invoices inv
    JOIN tenants t ON inv.tenant_id = t.id
    JOIN units u ON inv.unit_id = u.id
    WHERE inv.status IN ('unpaid', 'overdue')
    ORDER BY inv.due_date ASC LIMIT 5")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<!-- Dashboard Page Header -->
<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Property Dashboard</h1>
        <p class="page-subtitle">Welcome back, <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong>! Here is today's rental overview.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="<?php echo BASE_URL; ?>views/admin/utilities.php" class="btn btn-outline-secondary">
            <i class="fas fa-tachometer-alt me-1"></i> Log Meter Reading
        </a>
        <a href="<?php echo BASE_URL; ?>views/admin/billing.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i> Create Invoice
        </a>
    </div>
</div>

<!-- 4 Key Stat KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Stat 1: Total Units & Occupancy -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Occupancy Rate</span>
                    <div class="stat-value"><?php echo $occupancyRate; ?>%</div>
                    <small class="text-muted"><?php echo $occupiedUnits; ?> of <?php echo $totalUnits; ?> units occupied</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="fas fa-building"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 2: Monthly Collections -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label"><?php echo date('M Y'); ?> Collections</span>
                    <div class="stat-value text-success"><?php echo formatPeso($monthlyCollection); ?></div>
                    <small class="text-success"><i class="fas fa-arrow-up me-1"></i>Current month revenue</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-green">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 3: Outstanding Receivables -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Total Unpaid Dues</span>
                    <div class="stat-value text-danger"><?php echo formatPeso($totalReceivables); ?></div>
                    <small class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Pending collections</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-red">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 4: Active Maintenance -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Active Tickets</span>
                    <div class="stat-value text-warning"><?php echo $pendingMaintenance; ?></div>
                    <small class="text-muted">Repair requests in queue</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-amber">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-3 mb-4">
    <!-- Chart 1: Revenue Trends -->
    <div class="col-12 col-lg-8">
        <div class="custom-card h-100 mb-0">
            <div class="custom-card-header">
                <h5 class="custom-card-title"><i class="fas fa-chart-area text-primary me-2"></i>Collection & Receivables Trend</h5>
                <span class="badge bg-light text-muted border">Past 6 Months</span>
            </div>
            <div class="custom-card-body">
                <div style="height: 280px;">
                    <canvas id="revenueChart" 
                            data-months='["Mar", "Apr", "May", "Jun", "Jul", "Aug"]'
                            data-collections='[38000, 42500, 41000, 49000, 52500, <?php echo $monthlyCollection; ?>]'
                            data-receivables='[8000, 5000, 9500, 6000, 8500, <?php echo $totalReceivables; ?>]'>
                    </canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 2: Occupancy Breakdown -->
    <div class="col-12 col-lg-4">
        <div class="custom-card h-100 mb-0">
            <div class="custom-card-header">
                <h5 class="custom-card-title"><i class="fas fa-pie-chart text-success me-2"></i>Unit Occupancy</h5>
                <a href="<?php echo BASE_URL; ?>views/admin/units.php" class="btn btn-sm btn-link text-decoration-none p-0">Manage</a>
            </div>
            <div class="custom-card-body d-flex flex-column align-items-center justify-content-center">
                <div style="height: 220px; width: 100%;">
                    <canvas id="occupancyChart"
                            data-occupied="<?php echo $occupiedUnits; ?>"
                            data-vacant="<?php echo $vacantUnits; ?>"
                            data-maintenance="<?php echo $maintenanceUnits; ?>"
                            data-reserved="<?php echo $reservedUnits; ?>">
                    </canvas>
                </div>
                <div class="mt-3 text-center small text-muted">
                    Total Inventory: <strong><?php echo $totalUnits; ?> Units</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables: Recent Transactions & Urgent Invoices -->
<div class="row g-3">
    <!-- Recent Payments -->
    <div class="col-12 col-lg-6">
        <div class="custom-card mb-0">
            <div class="custom-card-header">
                <h5 class="custom-card-title"><i class="fas fa-check-circle text-success me-2"></i>Recent Collections</h5>
                <a href="<?php echo BASE_URL; ?>views/admin/payments.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Tenant / Unit</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No payments recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $pay): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($pay['unit_number']); ?></small>
                                    </td>
                                    <td class="fw-bold text-success"><?php echo formatPeso($pay['amount']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($pay['payment_method']); ?></span></td>
                                    <td class="text-muted small"><?php echo formatDate($pay['payment_date'], 'M d'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Outstanding / Overdue Invoices -->
    <div class="col-12 col-lg-6">
        <div class="custom-card mb-0">
            <div class="custom-card-header">
                <h5 class="custom-card-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Outstanding Dues</h5>
                <a href="<?php echo BASE_URL; ?>views/admin/billing.php" class="btn btn-sm btn-outline-secondary">Billing Center</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Tenant / Unit</th>
                            <th>Balance</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($urgentInvoices)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">All invoices are settled!</td></tr>
                        <?php else: ?>
                            <?php foreach ($urgentInvoices as $inv): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($inv['unit_number']); ?></small>
                                    </td>
                                    <td class="fw-bold text-danger"><?php echo formatPeso($inv['balance']); ?></td>
                                    <td class="text-muted small"><?php echo formatDate($inv['due_date'], 'M d, Y'); ?></td>
                                    <td><?php echo statusBadge($inv['status'], 'invoice'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
