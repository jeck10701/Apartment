<?php
/**
 * Super Admin: Audit Trail & Security Logs
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Security & Audit Logs';

$logs = $pdo->query("SELECT a.*, u.name as user_name, u.role as user_role, u.email as user_email
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC LIMIT 100")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">System Audit Trail</h1>
        <p class="page-subtitle">Historical records of logins, rent collections, invoice generations, and administrative changes.</p>
    </div>
</div>

<div class="custom-card mb-4">
    <div class="custom-card-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search action, user, or IP address..." data-table-search="auditTable">
                </div>
            </div>
            <div class="col-12 col-md-auto">
                <span class="badge bg-light text-dark border p-2">Total Log Records: <strong><?php echo count($logs); ?></strong></span>
            </div>
        </div>
    </div>
</div>

<div class="custom-card">
    <div class="table-responsive">
        <table class="table table-custom mb-0" id="auditTable">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>User / Role</th>
                    <th>Event Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No audit logs recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="text-muted font-monospace small">#<?php echo $l['id']; ?></td>
                            <td class="text-muted small"><?php echo formatDate($l['created_at'], 'M d, Y H:i:s'); ?></td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($l['action']); ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($l['user_name'] ?? 'Guest / System'); ?></div>
                                <?php if (!empty($l['user_role'])): ?>
                                    <small class="text-muted text-uppercase" style="font-size: 0.7rem;"><?php echo str_replace('_', ' ', $l['user_role']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-dark"><?php echo htmlspecialchars($l['details']); ?></span>
                            </td>
                            <td class="font-monospace small text-muted">
                                <?php echo htmlspecialchars($l['ip_address']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
