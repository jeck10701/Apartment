<?php
/**
 * Admin: Sub-meter Readings & Utility Computations (Water & Electricity)
 */
require_once dirname(dirname(__DIR__)) . '/includes/auth_check.php';

$pdo = getDBConnection();
$pageTitle = 'Sub-meter Readings & Utilities';

// Fetch active tenants with their assigned unit details
$activeTenants = $pdo->query("SELECT t.id as tenant_id, t.first_name, t.last_name, u.id as unit_id, u.unit_number, u.water_rate_per_unit, u.electric_rate_per_kwh, u.monthly_rent
    FROM tenants t
    JOIN units u ON t.unit_id = u.id
    WHERE t.status = 'active'
    ORDER BY u.unit_number ASC")->fetchAll();

// Fetch latest utility readings
$readingsHistory = $pdo->query("SELECT ur.*, u.unit_number, t.first_name, t.last_name
    FROM utility_readings ur
    JOIN units u ON ur.unit_id = u.id
    JOIN tenants t ON ur.tenant_id = t.id
    ORDER BY ur.reading_date DESC, ur.id DESC")->fetchAll();

include_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="d-md-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Sub-meter Utility Readings</h1>
        <p class="page-subtitle">Input water & electric sub-meter readings with automatic consumption math & bill generation.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Log Sub-meter Form Card -->
    <div class="col-12 col-xl-5">
        <div class="custom-card">
            <div class="custom-card-header bg-light">
                <h5 class="custom-card-title"><i class="fas fa-edit text-primary me-2"></i>Record Sub-meter Reading</h5>
            </div>
            <div class="custom-card-body">
                <form action="<?php echo BASE_URL; ?>controllers/UtilityController.php?action=record" method="POST">
                    <!-- Unit & Tenant Selection -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Occupied Unit & Tenant <span class="text-danger">*</span></label>
                        <select name="tenant_unit" id="tenant_unit_select" class="form-select" required onchange="onTenantUnitChanged(this)">
                            <option value="">-- Choose Unit / Tenant --</option>
                            <?php foreach ($activeTenants as $at): ?>
                                <option value="<?php echo $at['tenant_id'] . '_' . $at['unit_id']; ?>" 
                                        data-tenant-id="<?php echo $at['tenant_id']; ?>" 
                                        data-unit-id="<?php echo $at['unit_id']; ?>"
                                        data-water-rate="<?php echo $at['water_rate_per_unit']; ?>"
                                        data-electric-rate="<?php echo $at['electric_rate_per_kwh']; ?>">
                                    <?php echo htmlspecialchars($at['unit_number']); ?> — <?php echo htmlspecialchars($at['first_name'] . ' ' . $at['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="tenant_id" id="hidden_tenant_id">
                        <input type="hidden" name="unit_id" id="hidden_unit_id">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Billing Month</label>
                            <input type="month" name="billing_month" id="billing_month" class="form-control" value="<?php echo date('Y-m'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Reading Date</label>
                            <input type="date" name="reading_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <!-- Water Sub-meter Section -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold small text-dark"><i class="fas fa-tint text-info me-1"></i>Water Sub-meter (cu.m)</span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle" id="water_consumption_badge">0.00 cu.m</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label small text-muted mb-1">Previous</label>
                                <input type="number" step="0.01" min="0" name="prev_water_reading" id="prev_water_reading" class="form-control form-control-sm" placeholder="0.00" value="0">
                                <small class="text-muted d-block mt-1" id="water_previous_hint" style="font-size:0.68rem;">First reading starts at 0.00</small>
                            </div>
                            <div class="col-4">
                                <label class="form-label small text-muted mb-1">Current <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="curr_water_reading" id="curr_water_reading" class="form-control form-control-sm" placeholder="0.00" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small text-muted mb-1">Rate (₱/cu.m)</label>
                                <input type="number" step="0.01" name="water_rate" id="water_rate" class="form-control form-control-sm bg-light" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 pt-1 border-top small">
                            <span class="text-muted">Water Cost:</span>
                            <strong class="text-primary" id="water_total_cost">₱ 0.00</strong>
                        </div>
                    </div>

                    <!-- Electricity Sub-meter Section -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold small text-dark"><i class="fas fa-bolt text-warning me-1"></i>Electric Sub-meter (kWh)</span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle" id="elec_consumption_badge">0.00 kWh</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label small text-muted mb-1">Previous</label>
                                <input type="number" step="0.01" min="0" name="prev_electric_reading" id="prev_electric_reading" class="form-control form-control-sm" placeholder="0.00" value="0">
                                <small class="text-muted d-block mt-1" id="electric_previous_hint" style="font-size:0.68rem;">First reading starts at 0.00</small>
                            </div>
                            <div class="col-4">
                                <label class="form-label small text-muted mb-1">Current <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="curr_electric_reading" id="curr_electric_reading" class="form-control form-control-sm" placeholder="0.00" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small text-muted mb-1">Rate (₱/kWh)</label>
                                <input type="number" step="0.01" name="electric_rate" id="electric_rate" class="form-control form-control-sm bg-light" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 pt-1 border-top small">
                            <span class="text-muted">Electric Cost:</span>
                            <strong class="text-warning text-dark" id="electric_total_cost">₱ 0.00</strong>
                        </div>
                    </div>

                    <!-- Total Computation Banner -->
                    <div class="p-3 bg-primary-subtle border border-primary-subtle rounded-3 mb-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="small fw-semibold text-primary">Total Utilities Due:</span>
                            <div class="fs-5 fw-bold text-primary" id="utility_grand_total">₱ 0.00</div>
                        </div>
                        <i class="fas fa-calculator fs-3 text-primary opacity-50"></i>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="auto_generate_invoice" id="autoGenerateInv" checked>
                        <label class="form-check-label small fw-semibold text-dark" for="autoGenerateInv">
                            Auto-create Monthly Rent & Utility Invoice for Tenant
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i> Save Readings & Calculate
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Readings History Log -->
    <div class="col-12 col-xl-7">
        <div class="custom-card">
            <div class="custom-card-header">
                <h5 class="custom-card-title"><i class="fas fa-history text-secondary me-2"></i>Reading Records & Logs</h5>
                <span class="badge bg-light text-muted border">Latest Records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Unit / Tenant</th>
                            <th>Month</th>
                            <th>Water (cu.m)</th>
                            <th>Power (kWh)</th>
                            <th>Total Utility</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($readingsHistory)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No utility readings logged yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($readingsHistory as $rh): ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark"><?php echo htmlspecialchars($rh['unit_number']); ?></strong>
                                        <div class="small text-muted"><?php echo htmlspecialchars($rh['first_name'] . ' ' . $rh['last_name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo date('M Y', strtotime($rh['billing_month'] . '-01')); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo number_format($rh['water_consumption'], 2); ?> cu.m</div>
                                        <small class="text-muted"><?php echo formatPeso($rh['water_amount']); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo number_format($rh['electric_consumption'], 2); ?> kWh</div>
                                        <small class="text-muted"><?php echo formatPeso($rh['electric_amount']); ?></small>
                                    </td>
                                    <td class="fw-bold text-primary">
                                        <?php echo formatPeso($rh['water_amount'] + $rh['electric_amount']); ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo formatDate($rh['reading_date'], 'M d'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function money(value) {
    return '₱ ' + Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function calculateUtilityTotals() {
    const prevWater = parseFloat(document.getElementById('prev_water_reading')?.value) || 0;
    const currWater = parseFloat(document.getElementById('curr_water_reading')?.value) || 0;
    const waterRate = parseFloat(document.getElementById('water_rate')?.value) || 0;

    const prevElec = parseFloat(document.getElementById('prev_electric_reading')?.value) || 0;
    const currElec = parseFloat(document.getElementById('curr_electric_reading')?.value) || 0;
    const elecRate = parseFloat(document.getElementById('electric_rate')?.value) || 0;

    const waterConsumption = Math.max(0, currWater - prevWater);
    const elecConsumption = Math.max(0, currElec - prevElec);
    const waterAmount = waterConsumption * waterRate;
    const elecAmount = elecConsumption * elecRate;

    document.getElementById('water_consumption_badge').textContent = waterConsumption.toFixed(2) + ' cu.m';
    document.getElementById('elec_consumption_badge').textContent = elecConsumption.toFixed(2) + ' kWh';
    document.getElementById('water_total_cost').textContent = money(waterAmount);
    document.getElementById('electric_total_cost').textContent = money(elecAmount);
    document.getElementById('utility_grand_total').textContent = money(waterAmount + elecAmount);

    const waterInvalid = currWater < prevWater;
    const elecInvalid = currElec < prevElec;
    document.getElementById('curr_water_reading').classList.toggle('is-invalid', waterInvalid);
    document.getElementById('curr_electric_reading').classList.toggle('is-invalid', elecInvalid);
}

function setPreviousReadings(data) {
    const water = Number(data.previous_water ?? 0);
    const electric = Number(data.previous_electric ?? 0);

    document.getElementById('prev_water_reading').value = water.toFixed(2);
    document.getElementById('prev_electric_reading').value = electric.toFixed(2);

    const waterHint = document.getElementById('water_previous_hint');
    const electricHint = document.getElementById('electric_previous_hint');

    if (data.found) {
        waterHint.textContent = "Auto-filled from previous month's current reading";
        electricHint.textContent = "Auto-filled from previous month's current reading";
        waterHint.className = 'text-success d-block mt-1';
        electricHint.className = 'text-success d-block mt-1';
    } else {
        waterHint.textContent = 'First reading for this tenant/unit — starts at 0.00';
        electricHint.textContent = 'First reading for this tenant/unit — starts at 0.00';
        waterHint.className = 'text-muted d-block mt-1';
        electricHint.className = 'text-muted d-block mt-1';
    }
    calculateUtilityTotals();
}

function resetUtilityFormToZero() {
    document.getElementById('hidden_tenant_id').value = '';
    document.getElementById('hidden_unit_id').value = '';
    document.getElementById('prev_water_reading').value = '0.00';
    document.getElementById('curr_water_reading').value = '';
    document.getElementById('water_rate').value = '0.00';
    document.getElementById('prev_electric_reading').value = '0.00';
    document.getElementById('curr_electric_reading').value = '';
    document.getElementById('electric_rate').value = '0.00';
    document.getElementById('water_previous_hint').textContent = 'Select an occupied unit & tenant first';
    document.getElementById('electric_previous_hint').textContent = 'Select an occupied unit & tenant first';
    document.getElementById('water_previous_hint').className = 'text-muted d-block mt-1';
    document.getElementById('electric_previous_hint').className = 'text-muted d-block mt-1';
    calculateUtilityTotals();
}

function loadPreviousReadings() {
    const select = document.getElementById('tenant_unit_select');
    const month = document.getElementById('billing_month')?.value;
    const selected = select?.options[select.selectedIndex];
    if (!selected || !selected.value || !month) {
        resetUtilityFormToZero();
        return;
    }

    const tenantId = selected.dataset.tenantId;
    const unitId = selected.dataset.unitId;
    document.getElementById('hidden_tenant_id').value = tenantId || '';
    document.getElementById('hidden_unit_id').value = unitId || '';

    // Immediately show the rates saved for the selected unit.
    // The tenant/unit dropdown already contains the unit's current rates,
    // so the values appear as soon as the tenant + unit is selected.
    const baseUrl = '<?php echo BASE_URL; ?>controllers/UtilityController.php';
    const selectedWaterRate = Number(selected.dataset.waterRate || 0);
    const selectedElectricRate = Number(selected.dataset.electricRate || 0);
    document.getElementById('water_rate').value = selectedWaterRate.toFixed(2);
    document.getElementById('electric_rate').value = selectedElectricRate.toFixed(2);

    const rateUrl = baseUrl + '?action=get_unit_rates&unit_id=' + encodeURIComponent(unitId);
    fetch(rateUrl, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(response => response.json())
    .then(rateData => {
        if (rateData.success) {
            document.getElementById('water_rate').value = Number(rateData.water_rate || 0).toFixed(2);
            document.getElementById('electric_rate').value = Number(rateData.electric_rate || 0).toFixed(2);
            calculateUtilityTotals();
        }
    })
    .catch(() => {});

    document.getElementById('prev_water_reading').value = '0.00';
    document.getElementById('prev_electric_reading').value = '0.00';
    document.getElementById('water_previous_hint').textContent = 'Loading previous reading...';
    document.getElementById('electric_previous_hint').textContent = 'Loading previous reading...';
    calculateUtilityTotals();

    calculateUtilityTotals();

    // Load the previous month's CURRENT readings for this tenant/unit.
    fetch(baseUrl + '?action=get_previous_reading&tenant_id=' + encodeURIComponent(tenantId) + '&unit_id=' + encodeURIComponent(unitId) + '&billing_month=' + encodeURIComponent(month), {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setPreviousReadings(data);
        } else {
            setPreviousReadings({found:false, previous_water:0, previous_electric:0});
        }
    })
    .catch(() => {
        setPreviousReadings({found:false, previous_water:0, previous_electric:0});
    });
}

function onTenantUnitChanged(select) {
    if (!select || !select.value) {
        resetUtilityFormToZero();
        return;
    }
    loadPreviousReadings();
}

['prev_water_reading','curr_water_reading','water_rate','prev_electric_reading','curr_electric_reading','electric_rate'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', calculateUtilityTotals);
    document.getElementById(id)?.addEventListener('change', calculateUtilityTotals);
});

document.getElementById('billing_month')?.addEventListener('change', loadPreviousReadings);
document.addEventListener('DOMContentLoaded', function() {
    calculateUtilityTotals();
    if (document.getElementById('tenant_unit_select')?.value) loadPreviousReadings();
});
</script>

<?php include_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
