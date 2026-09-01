<?php
require_once dirname(__DIR__) . '/config/config.php';

$pdo = getDBConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'record') {
        requireRole(['admin', 'super_admin']);

        $invoiceId    = intval($_POST['invoice_id'] ?? 0);
        $amount       = floatval($_POST['amount'] ?? 0);
        $method       = trim($_POST['payment_method'] ?? 'Cash');
        $refNo        = trim($_POST['transaction_ref_no'] ?? '');
        $paymentDate  = $_POST['payment_date'] ?? date('Y-m-d');
        $notes        = trim($_POST['notes'] ?? '');
        $receivedBy   = $_SESSION['user_id'] ?? null;

        if ($invoiceId <= 0 || $amount <= 0) {
            setFlash('danger', 'Please enter a valid invoice and payment amount.');
            redirect(BASE_URL . 'views/admin/payments.php');
        }

        try {
            $pdo->beginTransaction();

            // Fetch invoice
            $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
            $invStmt->execute([$invoiceId]);
            $invoice = $invStmt->fetch();

            if (!$invoice) {
                throw new Exception('Invoice not found.');
            }

            $paymentRef = 'PAY-' . date('Ymd') . '-' . rand(1000, 9999);

            $payStmt = $pdo->prepare("INSERT INTO payments (invoice_id, tenant_id, payment_reference, amount, payment_method, transaction_ref_no, payment_date, status, notes, received_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?)");
            $payStmt->execute([$invoiceId, $invoice['tenant_id'], $paymentRef, $amount, $method, $refNo, $paymentDate, $notes, $receivedBy]);

            $newPaid = $invoice['paid_amount'] + $amount;
            $newBalance = max(0, $invoice['total_amount'] - $newPaid);
            $newStatus = ($newBalance <= 0) ? 'paid' : 'partially_paid';

            $updInv = $pdo->prepare("UPDATE invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?");
            $updInv->execute([$newPaid, $newBalance, $newStatus, $invoiceId]);

            $pdo->commit();

            logActivity('PAYMENT_RECORDED', "Recorded payment $paymentRef of ₱$amount for {$invoice['invoice_number']}.");
            setFlash('success', "Payment of " . formatPeso($amount) . " recorded successfully (Receipt Ref: $paymentRef).");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error recording payment: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/payments.php');
    }

    if ($action === 'submit_proof') {
        requireRole(['tenant', 'admin', 'super_admin']);

        $invoiceId   = intval($_POST['invoice_id'] ?? 0);
        $amount      = floatval($_POST['amount'] ?? 0);
        $method      = trim($_POST['payment_method'] ?? 'GCash');
        $refNo       = trim($_POST['transaction_ref_no'] ?? '');
        $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
        $notes       = trim($_POST['notes'] ?? '');

        $userId = $_SESSION['user_id'];
        // Lookup tenant_id directly from the chosen invoice for accurate multi-unit mapping
        $invStmt = $pdo->prepare("SELECT tenant_id FROM invoices WHERE id = ?");
        $invStmt->execute([$invoiceId]);
        $tenantId = $invStmt->fetchColumn();

        if (!$tenantId) {
            $tStmt = $pdo->prepare("SELECT id FROM tenants WHERE user_id = ? OR id = ? LIMIT 1");
            $tStmt->execute([$userId, intval($_POST['tenant_id'] ?? 0)]);
            $tenantId = $tStmt->fetchColumn();
        }

        if (!$tenantId || $invoiceId <= 0 || $amount <= 0) {
            setFlash('danger', 'Invalid payment submission parameters.');
            redirect(BASE_URL . 'views/tenant/billing.php');
        }

        $proofFileName = null;
        if (!empty($_FILES['proof_file']['name'])) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $fileExt = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));

            if (in_array($fileExt, $allowedExts)) {
                if (!file_exists(UPLOADS_PATH)) {
                    mkdir(UPLOADS_PATH, 0777, true);
                }
                $proofFileName = 'proof_' . time() . '_' . rand(100, 999) . '.' . $fileExt;
                move_uploaded_file($_FILES['proof_file']['tmp_name'], UPLOADS_PATH . $proofFileName);
            }
        }

        try {
            $paymentRef = 'SUB-' . date('Ymd') . '-' . rand(1000, 9999);
            $stmt = $pdo->prepare("INSERT INTO payments (invoice_id, tenant_id, payment_reference, amount, payment_method, transaction_ref_no, payment_date, proof_of_payment, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_verification', ?)");
            $stmt->execute([$invoiceId, $tenantId, $paymentRef, $amount, $method, $refNo, $paymentDate, $proofFileName, $notes]);

            logActivity('PROOF_SUBMITTED', "Tenant submitted proof of payment for Invoice #$invoiceId (Ref: $refNo).");
            setFlash('success', 'Your proof of payment has been submitted and is pending verification by property management.');
        } catch (Exception $e) {
            setFlash('danger', 'Error submitting proof of payment: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/tenant/billing.php');
    }

    if ($action === 'verify') {
        requireRole(['admin', 'super_admin']);

        $paymentId = intval($_POST['payment_id'] ?? 0);
        $decision  = trim($_POST['decision'] ?? 'confirmed'); // 'confirmed' or 'rejected'

        try {
            $pdo->beginTransaction();

            $payStmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
            $payStmt->execute([$paymentId]);
            $pay = $payStmt->fetch();

            if (!$pay) throw new Exception('Payment record not found.');

            if ($decision === 'confirmed') {
                // Update payment status
                $updPay = $pdo->prepare("UPDATE payments SET status = 'confirmed', received_by = ? WHERE id = ?");
                $updPay->execute([$_SESSION['user_id'], $paymentId]);

                // Update invoice
                $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
                $invStmt->execute([$pay['invoice_id']]);
                $inv = $invStmt->fetch();

                if ($inv) {
                    $newPaid = $inv['paid_amount'] + $pay['amount'];
                    $newBalance = max(0, $inv['total_amount'] - $newPaid);
                    $newStatus = ($newBalance <= 0) ? 'paid' : 'partially_paid';

                    $updInv = $pdo->prepare("UPDATE invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?");
                    $updInv->execute([$newPaid, $newBalance, $newStatus, $pay['invoice_id']]);
                }

                setFlash('success', 'Payment verified and credited to tenant invoice.');
            } else {
                $updPay = $pdo->prepare("UPDATE payments SET status = 'rejected' WHERE id = ?");
                $updPay->execute([$paymentId]);
                setFlash('warning', 'Payment proof has been rejected.');
            }

            $pdo->commit();
            logActivity('PAYMENT_VERIFIED', "Updated payment ID #$paymentId verification status to $decision.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error updating verification: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/payments.php');
    }
}

redirect(BASE_URL . 'views/admin/payments.php');
