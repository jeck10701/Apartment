<?php
/**
 * BillingController - Manages Invoices, Penalties, and SOA Generation
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'super_admin']);

$pdo = getDBConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// -------------------------------------------------------------
// GET UTILITY TOTALS FOR INVOICE GENERATION
// Pulls the saved sub-meter amounts for the selected tenant/month.
// -------------------------------------------------------------
if ($action === 'get_utility_for_invoice' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    $tenantId = intval($_GET['tenant_id'] ?? 0);
    $billingMonth = trim($_GET['billing_month'] ?? '');

    if ($tenantId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
        echo json_encode(['success' => false, 'message' => 'Invalid tenant or billing month.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT ur.id, ur.unit_id, ur.water_amount, ur.electric_amount, ur.water_consumption, ur.electric_consumption, ur.water_rate, ur.electric_rate
            FROM utility_readings ur
            WHERE ur.tenant_id = ? AND ur.billing_month = ?
            ORDER BY ur.id DESC
            LIMIT 1");
        $stmt->execute([$tenantId, $billingMonth]);
        $reading = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'found' => (bool)$reading,
            'utility_reading_id' => $reading ? (int)$reading['id'] : null,
            'water_amount' => $reading ? (float)$reading['water_amount'] : 0,
            'electricity_amount' => $reading ? (float)$reading['electric_amount'] : 0,
            'water_consumption' => $reading ? (float)$reading['water_consumption'] : 0,
            'electric_consumption' => $reading ? (float)$reading['electric_consumption'] : 0,
            'water_rate' => $reading ? (float)$reading['water_rate'] : 0,
            'electric_rate' => $reading ? (float)$reading['electric_rate'] : 0
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Unable to load saved utility reading.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // -------------------------------------------------------------
    // GENERATE NEW INVOICE
    // -------------------------------------------------------------
    if ($action === 'create') {
        $tenantId    = intval($_POST['tenant_id'] ?? 0);
        $periodStart = $_POST['billing_period_start'] ?? date('Y-m-01');
        $periodEnd   = $_POST['billing_period_end'] ?? date('Y-m-t');
        $dueDate     = $_POST['due_date'] ?? date('Y-m-05');
        $rentAmount  = floatval($_POST['rent_amount'] ?? 0);
        $waterAmount = floatval($_POST['water_amount'] ?? 0);
        $elecAmount  = floatval($_POST['electricity_amount'] ?? 0);
        $penalty     = floatval($_POST['penalty_amount'] ?? 0);
        $otherAmt    = floatval($_POST['other_charges'] ?? 0);
        $otherNotes  = trim($_POST['other_charges_notes'] ?? '');

        if ($tenantId <= 0 || $rentAmount <= 0) {
            setFlash('danger', 'Valid tenant and rent amount are required.');
            redirect(BASE_URL . 'views/admin/billing.php');
        }

        try {
            // Fetch tenant's unit ID
            $tStmt = $pdo->prepare("SELECT unit_id FROM tenants WHERE id = ?");
            $tStmt->execute([$tenantId]);
            $unitId = $tStmt->fetchColumn();

            if (!$unitId) {
                setFlash('danger', 'Tenant has no assigned unit.');
                redirect(BASE_URL . 'views/admin/billing.php');
            }

            // If a sub-meter reading was already saved for this tenant and billing month,
            // use its computed water/electric charges automatically. This keeps the invoice
            // synchronized with Sub-meter Readings instead of relying on manually typed amounts.
            $billingMonth = date('Y-m', strtotime($periodStart));
            $utilityReadingId = null;
            $utilityStmt = $pdo->prepare("SELECT id, water_amount, electric_amount FROM utility_readings
                WHERE tenant_id = ? AND unit_id = ? AND billing_month = ?
                ORDER BY id DESC LIMIT 1");
            $utilityStmt->execute([$tenantId, $unitId, $billingMonth]);
            $utility = $utilityStmt->fetch();

            if ($utility) {
                $utilityReadingId = (int)$utility['id'];
                $waterAmount = (float)$utility['water_amount'];
                $elecAmount = (float)$utility['electric_amount'];
            }

            $totalAmount = $rentAmount + $waterAmount + $elecAmount + $penalty + $otherAmt;
            $invoiceNumber = 'INV-' . date('Ymd', strtotime($periodStart)) . '-' . rand(100, 999);

            $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, tenant_id, unit_id, utility_reading_id, billing_period_start, billing_period_end, due_date, rent_amount, water_amount, electricity_amount, penalty_amount, other_charges, other_charges_notes, total_amount, balance, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')");
            $stmt->execute([$invoiceNumber, $tenantId, $unitId, $utilityReadingId, $periodStart, $periodEnd, $dueDate, $rentAmount, $waterAmount, $elecAmount, $penalty, $otherAmt, $otherNotes, $totalAmount, $totalAmount]);

            logActivity('INVOICE_CREATED', "Generated invoice $invoiceNumber for Total ₱$totalAmount.");
            setFlash('success', "Invoice $invoiceNumber generated successfully.");
        } catch (Exception $e) {
            setFlash('danger', 'Error generating invoice: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/billing.php');
    }

    // -------------------------------------------------------------
    // APPLY LATE PENALTY FEE
    // -------------------------------------------------------------
    if ($action === 'apply_penalty') {
        $invoiceId = intval($_POST['invoice_id'] ?? 0);
        $penaltyAmt = floatval($_POST['penalty_amount'] ?? 250.00);

        try {
            $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
            $invStmt->execute([$invoiceId]);
            $inv = $invStmt->fetch();

            if ($inv) {
                $newTotal = $inv['total_amount'] + $penaltyAmt;
                $newBalance = $inv['balance'] + $penaltyAmt;
                $newPenalty = $inv['penalty_amount'] + $penaltyAmt;

                $update = $pdo->prepare("UPDATE invoices SET penalty_amount = ?, total_amount = ?, balance = ?, status = 'overdue' WHERE id = ?");
                $update->execute([$newPenalty, $newTotal, $newBalance, $invoiceId]);

                logActivity('PENALTY_APPLIED', "Applied late fee penalty ₱$penaltyAmt to {$inv['invoice_number']}.");
                setFlash('success', "Late penalty fee of " . formatPeso($penaltyAmt) . " applied.");
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error applying penalty: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/billing.php');
    }

    // -------------------------------------------------------------
    // DELETE INVOICE
    // -------------------------------------------------------------
    if ($action === 'delete') {
        $invoiceId = intval($_POST['invoice_id'] ?? 0);

        try {
            $checkPay = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE invoice_id = ?");
            $checkPay->execute([$invoiceId]);
            if ($checkPay->fetchColumn() > 0) {
                setFlash('danger', 'Cannot delete invoice that has recorded payment transactions.');
                redirect(BASE_URL . 'views/admin/billing.php');
            }

            $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
            $stmt->execute([$invoiceId]);

            logActivity('INVOICE_DELETED', "Deleted invoice ID #$invoiceId.");
            setFlash('success', 'Invoice record deleted successfully.');
        } catch (Exception $e) {
            setFlash('danger', 'Error deleting invoice: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/billing.php');
    }
}

redirect(BASE_URL . 'views/admin/billing.php');
