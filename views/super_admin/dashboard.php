<?php
/**
 * Super Admin Dashboard
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Super Admin Dashboard';

// Metrics
$totalOwners = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalProperties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$totalUnits = $pdo->query("SELECT COUNT(*) FROM units")->fetchColumn();
$totalTenants = $pdo->query("SELECT COUNT(*) FROM tenants WHERE status = 'active'")->fetchColumn();
$systemTotalCollection = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'confirmed'")->fetchColumn();

// List of Property Owners
$owners = $pdo->query("SELECT u.*, p.name as property_name, p.address as property_address,
    (SELECT COUNT(*) FROM units WHERE property_id = p.id) as unit_count
    FROM users u
    LEFT JOIN properties p ON u.id = p.owner_id
    WHERE u.role = 'admin'
    ORDER BY u.created_at DESC")->fetchAll();

// Recent Audit Logs
$recentLogs = $pdo->query("SELECT a.*, u.name as user_name, u.role as user_role 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC LIMIT 6")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title"><i class="fas fa-shield-alt text-primary me-2"></i>Super Administrator Console</h1>
        <p class="page-subtitle">Master oversight of all property owners, building assets, audit trails, and core configurations.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="<?php echo BASE_URL; ?>views/super_admin/owners.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i> Add Property Owner
        </a>
    </div>
</div>

<!-- Super Admin KPI Metrics -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Property Owners</span>
                    <div class="stat-value text-primary"><?php echo $totalOwners; ?></div>
                    <small class="text-muted">Registered landlords</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-blue">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Total Properties</span>
                    <div class="stat-value text-dark"><?php echo $totalProperties; ?></div>
                    <small class="text-muted">Managed buildings</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-purple">
                    <i class="fas fa-city"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">Total Units</span>
                    <div class="stat-value text-success"><?php echo $totalUnits; ?></div>
                    <small class="text-success"><?php echo $totalTenants; ?> active tenants</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-green">
                    <i class="fas fa-door-open"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="stat-label">System Collections</span>
                    <div class="stat-value text-dark"><?php echo formatPeso($systemTotalCollection); ?></div>
                    <small class="text-muted">Lifetime transactions</small>
                </div>
                <div class="stat-icon-wrapper stat-icon-amber">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Owners List & Recent Logs -->
<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="custom-card mb-0">
            <div class="custom-card-header">
                <h5 class="custom-card-title"><i class="fas fa-users-cog text-primary me-2"></i>Registered Landlords & Properties</h5>
                <a href="<?php echo BASE_URL; ?>views/super_admin/owners.php" class="btn btn-sm btn-outline-secondary">Manage</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Owner Name</th>
                            <th>Property Name</th>
                            <th>Units</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($owners)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No owners registered.</td></tr>
                        <?php else: ?>
                            <?php foreach ($owners as $ow): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($ow['name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($ow['email']); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-primary"><?php echo htmlspecialchars($ow['property_name'] ?? '—'); ?></div>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;"><?php echo htmlspecialchars($ow['property_address'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo intval($ow['unit_count']); ?> Units</span>
                                    </td>
                                    <td>
                                        <?php if ($ow['status'] === 'active'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill"><?php echo ucfirst($ow['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Audit Logs -->
    <div class="col-12 col-lg-5">
        <div class="custom-card mb-0">
            <div class="custom-card-header">
                <h5 class="custom-card-title"><i class="fas fa-history text-secondary me-2"></i>Recent System Activity</h5>
                <a href="<?php echo BASE_URL; ?>views/super_admin/audit_logs.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="custom-card-body p-0">
                <ul class="list-group list-group-flush small">
                    <?php if (empty($recentLogs)): ?>
                        <li class="list-group-item text-center text-muted py-3">No logs recorded yet.</li>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark"><?php echo htmlspecialchars($log['action']); ?></strong>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?php echo formatDate($log['created_at'], 'M d, H:i'); ?></span>
                                </div>
                                <div class="text-muted"><?php echo htmlspecialchars($log['details']); ?></div>
                                <div class="text-secondary mt-1" style="font-size: 0.72rem;">
                                    By: <em><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></em> (<?php echo $log['ip_address']; ?>)
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
