<?php
/**
 * Admin: Unit / Room Management
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Units & Rooms';

$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('default_water_rate', 'default_electric_rate')")->fetchAll(PDO::FETCH_KEY_PAIR);
$defaultWaterRate = (float)($settingsRows['default_water_rate'] ?? 45.00);
$defaultElectricRate = (float)($settingsRows['default_electric_rate'] ?? 14.50);

// Fetch all units with property name and active tenant details
$units = $pdo->query("SELECT u.*, p.name as property_name, t.first_name, t.last_name, t.phone as tenant_phone
    FROM units u
    JOIN properties p ON u.property_id = p.id
    LEFT JOIN tenants t ON u.id = t.unit_id AND t.status = 'active'
    ORDER BY u.floor_level ASC, u.unit_number ASC")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Units & Rooms Management</h1>
        <p class="page-subtitle">Manage rental inventory, floor assignments, and pricing rates.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUnitModal">
            <i class="fas fa-plus-circle me-1"></i> Add New Unit
        </button>
    </div>
</div>

<!-- Search & Stats Bar -->
<div class="custom-card mb-4">
    <div class="custom-card-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search by unit number, type, or tenant..." data-table-search="unitsTable">
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <span class="badge bg-light text-dark border p-2"><i class="fas fa-layer-group text-primary me-1"></i> Total: <strong><?php echo count($units); ?></strong></span>
                <span class="badge bg-success-subtle text-success border border-success-subtle p-2"><i class="fas fa-check-circle me-1"></i> Available: <strong><?php echo count(array_filter($units, fn($u) => $u['status'] === 'vacant')); ?></strong></span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle p-2"><i class="fas fa-user-check me-1"></i> Occupied: <strong><?php echo count(array_filter($units, fn($u) => $u['status'] === 'occupied')); ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Units Master Table -->
<div class="custom-card">
    <div class="table-responsive">
        <table class="table table-custom mb-0" id="unitsTable">
            <thead>
                <tr>
                    <th>Unit #</th>
                    <th>Type / Floor</th>
                    <th>Monthly Rent</th>
                    <th>Deposit</th>
                    <th>Utility Rates</th>
                    <th>Current Tenant</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($units)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No units found. Click "Add New Unit" to create one.</td></tr>
                <?php else: ?>
                    <?php foreach ($units as $unit): ?>
                        <tr>
                            <td>
                                <strong class="text-dark fs-6"><?php echo htmlspecialchars($unit['unit_number']); ?></strong>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($unit['unit_type']); ?></div>
                                <small class="text-muted">Floor <?php echo $unit['floor_level']; ?> &bull; Max <?php echo $unit['max_occupants']; ?> pax</small>
                            </td>
                            <td class="fw-bold text-dark">
                                <?php echo formatPeso($unit['monthly_rent']); ?>
                            </td>
                            <td class="text-muted">
                                <?php echo formatPeso($unit['security_deposit']); ?>
                            </td>
                            <td>
                                <div class="small text-muted"><i class="fas fa-tint text-info me-1"></i>Water: ₱<?php echo number_format($unit['water_rate_per_unit'], 2); ?>/cu.m</div>
                                <div class="small text-muted"><i class="fas fa-bolt text-warning me-1"></i>Power: ₱<?php echo number_format($unit['electric_rate_per_kwh'], 2); ?>/kWh</div>
                            </td>
                            <td>
                                <?php if ($unit['status'] === 'occupied' && !empty($unit['first_name'])): ?>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($unit['first_name'] . ' ' . $unit['last_name']); ?></div>
                                    <small class="text-muted"><i class="fas fa-phone-alt me-1" style="font-size:0.7rem;"></i><?php echo htmlspecialchars($unit['tenant_phone']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo statusBadge($unit['status'], 'unit'); ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary me-1" 
                                        onclick="openEditUnitModal(<?php echo htmlspecialchars(json_encode($unit)); ?>)" 
                                        title="Edit Unit Details">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($unit['status'] !== 'occupied'): ?>
                                    <form action="<?php echo BASE_URL; ?>controllers/UnitController.php?action=delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete Unit <?php echo $unit['unit_number']; ?>?');">
                                        <input type="hidden" name="unit_id" value="<?php echo $unit['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Unit">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Unit -->
<div class="modal fade" id="addUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-door-open text-primary me-2"></i>Add New Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/UnitController.php?action=add" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Unit Number / Code <span class="text-danger">*</span></label>
                            <input type="text" name="unit_number" class="form-control" placeholder="e.g. Unit 305" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Floor Level</label>
                            <input type="number" name="floor_level" class="form-control" value="1" min="1" max="20" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Unit Type <span class="text-danger">*</span></label>
                            <select name="unit_type" class="form-select" required>
                                <option value="Studio">Studio Unit</option>
                                <option value="Studio Deluxe">Studio Deluxe</option>
                                <option value="1-Bedroom">1-Bedroom</option>
                                <option value="2-Bedroom">2-Bedroom</option>
                                <option value="Bedspace">Bedspace</option>
                                <option value="Commercial Space">Commercial Space</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Max Occupants</label>
                            <input type="number" name="max_occupants" class="form-control" value="2" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Monthly Rent (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="monthly_rent" class="form-control" placeholder="8500.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Security Deposit (₱)</label>
                            <input type="number" step="0.01" name="security_deposit" class="form-control" placeholder="8500.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Water Sub-meter Rate (₱/cu.m)</label>
                            <input type="number" step="0.01" name="water_rate_per_unit" class="form-control" value="<?php echo number_format($defaultWaterRate, 2, '.', ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Electricity Sub-meter Rate (₱/kWh)</label>
                            <input type="number" step="0.01" name="electric_rate_per_kwh" class="form-control" value="<?php echo number_format($defaultElectricRate, 2, '.', ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Unit Description / Features</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="e.g. Equipped with private bathroom, sink, and sub-meters."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Unit -->
<div class="modal fade" id="editUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit text-primary me-2"></i>Edit Unit Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>controllers/UnitController.php?action=edit" method="POST">
                <input type="hidden" name="unit_id" id="edit_unit_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Unit Number / Code</label>
                            <input type="text" name="unit_number" id="edit_unit_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Floor Level</label>
                            <input type="number" name="floor_level" id="edit_floor_level" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Unit Type</label>
                            <select name="unit_type" id="edit_unit_type" class="form-select" required>
                                <option value="Studio">Studio Unit</option>
                                <option value="Studio Deluxe">Studio Deluxe</option>
                                <option value="1-Bedroom">1-Bedroom</option>
                                <option value="2-Bedroom">2-Bedroom</option>
                                <option value="Bedspace">Bedspace</option>
                                <option value="Commercial Space">Commercial Space</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Unit Status</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="vacant">Vacant / Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="maintenance">Under Maintenance</option>
                                <option value="reserved">Reserved</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Monthly Rent (₱)</label>
                            <input type="number" step="0.01" name="monthly_rent" id="edit_monthly_rent" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Security Deposit (₱)</label>
                            <input type="number" step="0.01" name="security_deposit" id="edit_security_deposit" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Water Rate (₱/cu.m)</label>
                            <input type="number" step="0.01" name="water_rate_per_unit" id="edit_water_rate" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Electric Rate (₱/kWh)</label>
                            <input type="number" step="0.01" name="electric_rate_per_kwh" id="edit_electric_rate" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Unit Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditUnitModal(unit) {
    document.getElementById('edit_unit_id').value = unit.id;
    document.getElementById('edit_unit_number').value = unit.unit_number;
    document.getElementById('edit_floor_level').value = unit.floor_level;
    document.getElementById('edit_unit_type').value = unit.unit_type;
    document.getElementById('edit_status').value = unit.status;
    document.getElementById('edit_monthly_rent').value = unit.monthly_rent;
    document.getElementById('edit_security_deposit').value = unit.security_deposit;
    document.getElementById('edit_water_rate').value = unit.water_rate_per_unit;
    document.getElementById('edit_electric_rate').value = unit.electric_rate_per_kwh;
    document.getElementById('edit_description').value = unit.description || '';
    
    new bootstrap.Modal(document.getElementById('editUnitModal')).show();
}
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
