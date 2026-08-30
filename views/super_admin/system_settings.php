<?php
/**
 * Super Admin: Global System Configuration
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'System Settings';

$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Global System Settings</h1>
        <p class="page-subtitle">Configure system branding, default utility tariffs, penalty charges, and payment destination accounts.</p>
    </div>
</div>

<form action="<?php echo BASE_URL; ?>controllers/AdminController.php?action=update_settings" method="POST">
    <div class="row g-4">
        <!-- System Branding & Company Profile -->
        <div class="col-12 col-lg-6">
            <div class="custom-card mb-4">
                <div class="custom-card-header bg-light">
                    <h5 class="custom-card-title"><i class="fas fa-building text-primary me-2"></i>Branding & Management Details</h5>
                </div>
                <div class="custom-card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Application / System Name</label>
                        <input type="text" name="system_name" class="form-control" value="<?php echo htmlspecialchars($settingsRows['system_name'] ?? 'ResiPro Apartment Management'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Currency Symbol</label>
                        <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars($settingsRows['currency_symbol'] ?? '₱'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Management Official Email</label>
                        <input type="email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($settingsRows['company_email'] ?? 'management@resipro.ph'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Management Contact Number</label>
                        <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($settingsRows['company_phone'] ?? '+63 917 555 8921'); ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Office / Property Address</label>
                        <textarea name="company_address" class="form-control" rows="2"><?php echo htmlspecialchars($settingsRows['company_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tariff Rates & Payment Channels -->
        <div class="col-12 col-lg-6">
            <!-- Utility & Penalties -->
            <div class="custom-card mb-4">
                <div class="custom-card-header bg-light">
                    <h5 class="custom-card-title"><i class="fas fa-calculator text-primary me-2"></i>Default Rates & Penalties</h5>
                </div>
                <div class="custom-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Default Water Tariff (₱/cu.m)</label>
                            <input type="number" step="0.01" name="default_water_rate" class="form-control" value="<?php echo htmlspecialchars($settingsRows['default_water_rate'] ?? '45.00'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Default Power Tariff (₱/kWh)</label>
                            <input type="number" step="0.01" name="default_electric_rate" class="form-control" value="<?php echo htmlspecialchars($settingsRows['default_electric_rate'] ?? '14.50'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Default Overdue Penalty Fee (₱)</label>
                            <input type="number" step="0.01" name="default_penalty_rate" class="form-control" value="<?php echo htmlspecialchars($settingsRows['default_penalty_rate'] ?? '250.00'); ?>" required>
                            <small class="text-muted">Standard penalty applied to overdue rent payments.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Destination Accounts -->
            <div class="custom-card mb-4">
                <div class="custom-card-header bg-light">
                    <h5 class="custom-card-title"><i class="fas fa-qrcode text-primary me-2"></i>Official Payment Receiving Channels</h5>
                </div>
                <div class="custom-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">GCash Account Name</label>
                            <input type="text" name="payment_gcash_name" class="form-control" value="<?php echo htmlspecialchars($settingsRows['payment_gcash_name'] ?? 'JUAN DELA CRUZ'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">GCash Mobile Number</label>
                            <input type="text" name="payment_gcash_number" class="form-control" value="<?php echo htmlspecialchars($settingsRows['payment_gcash_number'] ?? '0917-555-8921'); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Bank Deposit / Transfer Info</label>
                            <textarea name="payment_bank_info" class="form-control" rows="2"><?php echo htmlspecialchars($settingsRows['payment_bank_info'] ?? 'BDO Unibank | Account: 0048-1290-3456 (Juan Dela Cruz)'); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary px-4 py-2">
                <i class="fas fa-save me-1"></i> Save Global Settings
            </button>
        </div>
    </div>
</form>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
