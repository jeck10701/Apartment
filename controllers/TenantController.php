<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'super_admin']);

$pdo = getDBConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'add') {
        $unitId       = intval($_POST['unit_id'] ?? 0);
        $firstName    = trim($_POST['first_name'] ?? '');
        $lastName     = trim($_POST['last_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
        $idType       = trim($_POST['id_type'] ?? 'UMID / National ID');
        $idNumber     = trim($_POST['id_number'] ?? '');
        $leaseStart   = $_POST['lease_start'] ?? date('Y-m-d');
        $leaseEnd     = $_POST['lease_end'] ?? date('Y-m-d', strtotime('+1 year'));
        $rentDueDay   = intval($_POST['rent_due_day'] ?? 5);
        $depositPaid  = floatval($_POST['deposit_paid'] ?? 0);
        $advancePaid  = floatval($_POST['advance_paid'] ?? 0);
        $notes        = trim($_POST['notes'] ?? '');
        $createAccount = isset($_POST['create_portal_account']) ? true : false;

        if (empty($firstName) || empty($lastName) || empty($phone) || $unitId <= 0) {
            setFlash('danger', 'First name, last name, phone, and room assignment are required.');
            redirect(BASE_URL . 'views/admin/tenants.php');
        }

        try {
            $pdo->beginTransaction();

            $userId = null;
            if ($createAccount) {
                $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $lastName));
                $userEmail = !empty($email) ? $email : $username . '@tenant.local';
                $defaultPass = 'tenant123';
                $hashedPass = password_hash($defaultPass, PASSWORD_DEFAULT);

                $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $checkUser->execute([$username, $userEmail]);
                if ($existing = $checkUser->fetch()) {
                    $userId = $existing['id'];
                } else {
                    $createUser = $pdo->prepare("INSERT INTO users (name, username, email, password, role, phone, status) VALUES (?, ?, ?, ?, 'tenant', ?, 'active')");
                    $createUser->execute([$firstName . ' ' . $lastName, $username, $userEmail, $hashedPass, $phone]);
                    $userId = $pdo->lastInsertId();
                }
            }

            $stmt = $pdo->prepare("INSERT INTO tenants (user_id, unit_id, first_name, last_name, email, phone, emergency_contact_name, emergency_contact_phone, id_type, id_number, lease_start, lease_end, rent_due_day, deposit_paid, advance_paid, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([$userId, $unitId, $firstName, $lastName, $email, $phone, $emergencyName, $emergencyPhone, $idType, $idNumber, $leaseStart, $leaseEnd, $rentDueDay, $depositPaid, $advancePaid, $notes]);

            $updateUnit = $pdo->prepare("UPDATE units SET status = 'occupied' WHERE id = ?");
            $updateUnit->execute([$unitId]);

            $pdo->commit();

            logActivity('TENANT_REGISTERED', "Registered new tenant $firstName $lastName for Unit ID #$unitId.");
            setFlash('success', "Tenant $firstName $lastName successfully registered and assigned to unit.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error registering tenant: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/tenants.php');
    }

    if ($action === 'edit') {
        $tenantId     = intval($_POST['tenant_id'] ?? 0);
        $unitId       = ($_POST['unit_id'] ?? '') !== '' ? intval($_POST['unit_id']) : null;
        $firstName    = trim($_POST['first_name'] ?? '');
        $lastName     = trim($_POST['last_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
        $idType       = trim($_POST['id_type'] ?? '');
        $idNumber     = trim($_POST['id_number'] ?? '');
        $leaseStart   = $_POST['lease_start'] ?? '';
        $leaseEnd     = $_POST['lease_end'] ?? '';
        $rentDueDay   = intval($_POST['rent_due_day'] ?? 5);
        $depositPaid  = floatval($_POST['deposit_paid'] ?? 0);
        $advancePaid  = floatval($_POST['advance_paid'] ?? 0);
        $notes        = trim($_POST['notes'] ?? '');

        if ($tenantId <= 0 || empty($firstName) || empty($lastName)) {
            setFlash('danger', 'Invalid tenant update submission.');
            redirect(BASE_URL . 'views/admin/tenants.php');
        }

        try {
            $pdo->beginTransaction();

            $oldStmt = $pdo->prepare("SELECT unit_id FROM tenants WHERE id = ? LIMIT 1");
            $oldStmt->execute([$tenantId]);
            $oldTenant = $oldStmt->fetch();
            $oldUnitId = $oldTenant ? ($oldTenant['unit_id'] !== null ? intval($oldTenant['unit_id']) : null) : null;

            if ($unitId !== null) {
                $unitCheck = $pdo->prepare("SELECT id, status FROM units WHERE id = ? LIMIT 1");
                $unitCheck->execute([$unitId]);
                $selectedUnit = $unitCheck->fetch();
                if (!$selectedUnit) {
                    throw new Exception('Selected unit does not exist.');
                }
                if ($selectedUnit['status'] === 'occupied' && $unitId !== $oldUnitId) {
                    throw new Exception('The selected unit is already occupied. Please choose a vacant unit.');
                }
            }

            $stmt = $pdo->prepare("UPDATE tenants SET unit_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?, emergency_contact_name = ?, emergency_contact_phone = ?, id_type = ?, id_number = ?, lease_start = ?, lease_end = ?, rent_due_day = ?, deposit_paid = ?, advance_paid = ?, notes = ? WHERE id = ?");
            $stmt->execute([$unitId, $firstName, $lastName, $email, $phone, $emergencyName, $emergencyPhone, $idType, $idNumber, $leaseStart, $leaseEnd, $rentDueDay, $depositPaid, $advancePaid, $notes, $tenantId]);

            if ($oldUnitId !== null && $oldUnitId !== $unitId) {
                $vacate = $pdo->prepare("UPDATE units SET status = 'vacant' WHERE id = ?");
                $vacate->execute([$oldUnitId]);
            }
            if ($unitId !== null && $oldUnitId !== $unitId) {
                $occupy = $pdo->prepare("UPDATE units SET status = 'occupied' WHERE id = ?");
                $occupy->execute([$unitId]);
            }

            $pdo->commit();

            logActivity('TENANT_UPDATED', "Updated details for tenant $firstName $lastName (ID: $tenantId).");
            setFlash('success', "Tenant details updated successfully.");
        } catch (Exception $e) {
            setFlash('danger', 'Error updating tenant: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/tenants.php');
    }

    if ($action === 'move_out') {
        $tenantId = intval($_POST['tenant_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            $getTenant = $pdo->prepare("SELECT unit_id, first_name, last_name FROM tenants WHERE id = ?");
            $getTenant->execute([$tenantId]);
            $t = $getTenant->fetch();

            if ($t) {

                $stmt = $pdo->prepare("UPDATE tenants SET status = 'moved_out' WHERE id = ?");
                $stmt->execute([$tenantId]);

                // Vacate the unit
                $vacate = $pdo->prepare("UPDATE units SET status = 'vacant' WHERE id = ?");
                $vacate->execute([$t['unit_id']]);

                $pdo->commit();
                logActivity('TENANT_MOVED_OUT', "Processed move-out for {$t['first_name']} {$t['last_name']} from Unit ID #{$t['unit_id']}.");
                setFlash('success', "Tenant check-out processed. The unit is now marked as Vacant.");
            } else {
                $pdo->rollBack();
                setFlash('danger', 'Tenant record not found.');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error processing move out: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/tenants.php');
    }
}

redirect(BASE_URL . 'views/admin/tenants.php');
