<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'super_admin']);

$pdo = getDBConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'get_unit_rates' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    $unitId = intval($_GET['unit_id'] ?? 0);
    if ($unitId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid unit.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, unit_number, water_rate_per_unit, electric_rate_per_kwh, monthly_rent FROM units WHERE id = ? LIMIT 1");
        $stmt->execute([$unitId]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$unit) {
            echo json_encode(['success' => false, 'message' => 'Unit not found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'unit_id' => (int)$unit['id'],
            'unit_number' => $unit['unit_number'],
            'water_rate' => (float)($unit['water_rate_per_unit'] ?? 0),
            'electric_rate' => (float)($unit['electric_rate_per_kwh'] ?? 0),
            'monthly_rent' => (float)($unit['monthly_rent'] ?? 0)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Unable to load unit rates.']);
    }
    exit;
}

if ($action === 'get_previous_reading' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    $tenantId = intval($_GET['tenant_id'] ?? 0);
    $unitId = intval($_GET['unit_id'] ?? 0);
    $billingMonth = trim($_GET['billing_month'] ?? '');

    if ($tenantId <= 0 || $unitId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
        echo json_encode(['success' => false, 'message' => 'Invalid tenant, unit, or billing month.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT curr_water_reading, curr_electric_reading, billing_month
            FROM utility_readings
            WHERE tenant_id = ? AND unit_id = ? AND billing_month < ?
            ORDER BY billing_month DESC, id DESC
            LIMIT 1");
        $stmt->execute([$tenantId, $unitId, $billingMonth]);
        $previous = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'found' => (bool)$previous,
            'previous_water' => $previous ? (float)$previous['curr_water_reading'] : 0,
            'previous_electric' => $previous ? (float)$previous['curr_electric_reading'] : 0,
            'source_month' => $previous['billing_month'] ?? null
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Unable to load previous reading.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'record') {
        $unitId       = intval($_POST['unit_id'] ?? 0);
        $tenantId     = intval($_POST['tenant_id'] ?? 0);
        $billingMonth = trim($_POST['billing_month'] ?? date('Y-m'));
        $readingDate  = $_POST['reading_date'] ?? date('Y-m-d');

        $prevWater = floatval($_POST['prev_water_reading'] ?? 0);
        $currWater = floatval($_POST['curr_water_reading'] ?? 0);

        $prevElec = floatval($_POST['prev_electric_reading'] ?? 0);
        $currElec = floatval($_POST['curr_electric_reading'] ?? 0);

        if ($unitId <= 0 || $tenantId <= 0) {
            setFlash('danger', 'Please select a valid unit and tenant.');
            redirect(BASE_URL . 'views/admin/utilities.php');
        }

        $assignmentStmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? AND unit_id = ? AND status = 'active' LIMIT 1");
        $assignmentStmt->execute([$tenantId, $unitId]);
        if (!$assignmentStmt->fetch()) {
            setFlash('danger', 'The selected tenant is not currently assigned to that unit.');
            redirect(BASE_URL . 'views/admin/utilities.php');
        }

        $rateStmt = $pdo->prepare("SELECT water_rate_per_unit, electric_rate_per_kwh FROM units WHERE id = ? LIMIT 1");
        $rateStmt->execute([$unitId]);
        $unitRates = $rateStmt->fetch();
        if (!$unitRates) {
            setFlash('danger', 'Selected unit was not found.');
            redirect(BASE_URL . 'views/admin/utilities.php');
        }
        $waterRate = floatval($unitRates['water_rate_per_unit'] ?? 0);
        $elecRate  = floatval($unitRates['electric_rate_per_kwh'] ?? 0);

        $autoGenerateInvoice = isset($_POST['auto_generate_invoice']) ? true : false;

        if ($currWater < $prevWater || $currElec < $prevElec) {
            setFlash('danger', 'Current reading cannot be lower than previous reading.');
            redirect(BASE_URL . 'views/admin/utilities.php');
        }

        $waterConsumption = max(0, $currWater - $prevWater);
        $waterAmount      = $waterConsumption * $waterRate;

        $elecConsumption  = max(0, $currElec - $prevElec);
        $elecAmount       = $elecConsumption * $elecRate;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO utility_readings (unit_id, tenant_id, billing_month, prev_water_reading, curr_water_reading, water_consumption, water_rate, water_amount, prev_electric_reading, curr_electric_reading, electric_consumption, electric_rate, electric_amount, reading_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$unitId, $tenantId, $billingMonth, $prevWater, $currWater, $waterConsumption, $waterRate, $waterAmount, $prevElec, $currElec, $elecConsumption, $elecRate, $elecAmount, $readingDate]);
            $readingId = $pdo->lastInsertId();

            if ($autoGenerateInvoice) {

                $getUnit = $pdo->prepare("SELECT monthly_rent, unit_number FROM units WHERE id = ?");
                $getUnit->execute([$unitId]);
                $unit = $getUnit->fetch();
                $rentAmount = floatval($unit['monthly_rent'] ?? 0);

                $getTenant = $pdo->prepare("SELECT rent_due_day FROM tenants WHERE id = ?");
                $getTenant->execute([$tenantId]);
                $t = $getTenant->fetch();
                $dueDay = intval($t['rent_due_day'] ?? 5);

                $invoiceNumber = 'INV-' . str_replace('-', '', $billingMonth) . '-' . str_pad($unitId, 3, '0', STR_PAD_LEFT);
                $periodStart = $billingMonth . '-01';
                $periodEnd   = date('Y-m-t', strtotime($periodStart));
                $dueDate     = date('Y-m-' . str_pad($dueDay, 2, '0', STR_PAD_LEFT), strtotime($periodStart));

                $totalAmount = $rentAmount + $waterAmount + $elecAmount;

                $invStmt = $pdo->prepare("INSERT INTO invoices (invoice_number, tenant_id, unit_id, utility_reading_id, billing_period_start, billing_period_end, due_date, rent_amount, water_amount, electricity_amount, total_amount, balance, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')");
                $invStmt->execute([$invoiceNumber, $tenantId, $unitId, $readingId, $periodStart, $periodEnd, $dueDate, $rentAmount, $waterAmount, $elecAmount, $totalAmount, $totalAmount]);
            }

            $pdo->commit();
            logActivity('UTILITY_LOGGED', "Recorded utility meter reading for Unit #$unitId for month $billingMonth.");
            setFlash('success', "Utility reading saved successfully" . ($autoGenerateInvoice ? " and monthly invoice has been generated." : "."));
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error recording utility readings: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/utilities.php');
    }
}

redirect(BASE_URL . 'views/admin/utilities.php');
