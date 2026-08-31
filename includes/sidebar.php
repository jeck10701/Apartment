<?php

$currentUser = currentUser();
$role = $currentUser['role'] ?? 'tenant';
$currentScript = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isNavActive($page, $dir = '') {
    global $currentScript, $currentDir;
    if ($dir && $currentDir !== $dir) return '';
    return ($currentScript === $page) ? 'active' : '';
}
?>
<aside id="sidebar">

    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-building"></i>
        </div>
        <div>
            <h6 class="brand-title">ResiPro</h6>
            <p class="brand-subtitle">
                <?php 
                if ($role === 'super_admin') echo 'Super Admin Portal';
                elseif ($role === 'admin') echo 'Property Management';
                else echo 'Tenant Portal';
                ?>
            </p>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'super_admin'): ?>

            <div class="nav-section-title">Overview</div>
            <a href="<?php echo BASE_URL; ?>views/super_admin/dashboard.php" class="sidebar-link <?php echo isNavActive('dashboard.php', 'super_admin'); ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Super Dashboard</span>
            </a>

            <div class="nav-section-title">Administration</div>
            <a href="<?php echo BASE_URL; ?>views/super_admin/owners.php" class="sidebar-link <?php echo isNavActive('owners.php', 'super_admin'); ?>">
                <i class="fas fa-user-tie"></i>
                <span>User Accounts</span>
            </a>
            <a href="<?php echo BASE_URL; ?>views/super_admin/system_settings.php" class="sidebar-link <?php echo isNavActive('system_settings.php', 'super_admin'); ?>">
                <i class="fas fa-sliders-h"></i>
                <span>System Settings</span>
            </a>
            <a href="<?php echo BASE_URL; ?>views/super_admin/audit_logs.php" class="sidebar-link <?php echo isNavActive('audit_logs.php', 'super_admin'); ?>">
                <i class="fas fa-history"></i>
                <span>Audit Logs</span>
            </a>

        <?php elseif ($role === 'admin'): ?>
            <div class="nav-section-title">Overview</div>
            <a href="<?php echo BASE_URL; ?>views/admin/dashboard.php" class="sidebar-link <?php echo isNavActive('dashboard.php', 'admin'); ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>

            <div class="nav-section-title">Property & Tenants</div>
            <a href="<?php echo BASE_URL; ?>views/admin/units.php" class="sidebar-link <?php echo isNavActive('units.php', 'admin'); ?>">
                <i class="fas fa-door-open"></i>
                <span>Units / Rooms</span>
            </a>
            <a href="<?php echo BASE_URL; ?>views/admin/tenants.php" class="sidebar-link <?php echo isNavActive('tenants.php', 'admin'); ?>">
                <i class="fas fa-users"></i>
                <span>Tenants Masterlist</span>
            </a>

            <div class="nav-section-title">Billing & Finance</div>
            <a href="<?php echo BASE_URL; ?>views/admin/utilities.php" class="sidebar-link <?php echo isNavActive('utilities.php', 'admin'); ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Sub-meter Readings</span>
            </a>
            <a href="<?php echo BASE_URL; ?>views/admin/billing.php" class="sidebar-link <?php echo isNavActive('billing.php', 'admin'); ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Invoices & Billing</span>
            </a>
            <a href="<?php echo BASE_URL; ?>views/admin/payments.php" class="sidebar-link <?php echo isNavActive('payments.php', 'admin'); ?>">
                <i class="fas fa-receipt"></i>
                <span>Payment Collections</span>
            </a>

            <div class="nav-section-title">Operations</div>
            <a href="<?php echo BASE_URL; ?>views/admin/maintenance.php" class="sidebar-link <?php echo isNavActive('maintenance.php', 'admin'); ?>">
                <i class="fas fa-tools"></i>
                <span>Maintenance Tickets</span>
            </a>
            <a href="<?php echo BASE_URL; ?>views/admin/reports.php" class="sidebar-link <?php echo isNavActive('reports.php', 'admin'); ?>">
                <i class="fas fa-chart-line"></i>
                <span>Financial Reports</span>
            </a>

        <?php else: ?>

            <div class="nav-section-title">My Account</div>
            <a href="<?php echo BASE_URL; ?>views/tenant/dashboard.php" class="sidebar-link <?php echo isNavActive('dashboard.php', 'tenant'); ?>">
                <i class="fas fa-home"></i>
                <span>My Dashboard</span>
            </a>
            <a href="<?php echo BASE_URL; ?>views/tenant/billing.php" class="sidebar-link <?php echo isNavActive('billing.php', 'tenant'); ?>">
                <i class="fas fa-file-invoice"></i>
                <span>My Bills & Payments</span>
            </a>
            <a href="<?php echo BASE_URL; ?>views/tenant/maintenance.php" class="sidebar-link <?php echo isNavActive('maintenance.php', 'tenant'); ?>">
                <i class="fas fa-wrench"></i>
                <span>Request Maintenance</span>
            </a>
        <?php endif; ?>

        <div class="nav-section-title">Account</div>
        <a href="<?php echo BASE_URL; ?>views/shared/profile.php" class="sidebar-link <?php echo isNavActive('profile.php', 'shared'); ?>">
            <i class="fas fa-user-cog"></i>
            <span>My Profile</span>
        </a>
        <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-link text-danger-emphasis">
            <i class="fas fa-sign-out-alt text-danger"></i>
            <span class="text-danger">Logout</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($currentUser['avatar'])): ?>
                <img src="<?php echo BASE_URL . htmlspecialchars($currentUser['avatar']); ?>" alt="Profile" class="rounded-circle border" style="width:34px;height:34px;object-fit:cover;">
            <?php else: ?>
                <div class="user-avatar-badge" style="width: 34px; height: 34px; font-size: 0.85rem;">
                    <?php echo strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="overflow-hidden">
                <p class="mb-0 text-white text-truncate fw-semibold" style="font-size: 0.825rem;">
                    <?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?>
                </p>
                <small class="text-secondary text-capitalize" style="font-size: 0.725rem;">
                    <?php echo str_replace('_', ' ', $currentUser['role'] ?? ''); ?>
                </small>
            </div>
        </div>
    </div>
</aside>
