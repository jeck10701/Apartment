<?php
/**
 * AdminController - Handles Super Administrator Actions (Owners, Properties, Settings)
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['super_admin']);

$pdo = getDBConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // -------------------------------------------------------------
    // ADD NEW PROPERTY OWNER
    // -------------------------------------------------------------
    if ($action === 'add_owner') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? 'admin123');
        $propName = trim($_POST['property_name'] ?? '');
        $propAddr = trim($_POST['property_address'] ?? '');

        if (empty($name) || empty($username) || empty($email) || empty($propName)) {
            setFlash('danger', 'Owner name, username, email, and Property name are required.');
            redirect(BASE_URL . 'views/super_admin/owners.php');
        }

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                throw new Exception('Username or email already exists.');
            }

            $hashedPass = password_hash($password, PASSWORD_DEFAULT);
            $userStmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role, phone, status) VALUES (?, ?, ?, ?, 'admin', ?, 'active')");
            $userStmt->execute([$name, $username, $email, $hashedPass, $phone]);
            $ownerId = $pdo->lastInsertId();

            // Create initial property for this owner
            $propStmt = $pdo->prepare("INSERT INTO properties (owner_id, name, address) VALUES (?, ?, ?)");
            $propStmt->execute([$ownerId, $propName, $propAddr]);

            $pdo->commit();
            logActivity('OWNER_CREATED', "Created new property owner account for $name ($propName).");
            setFlash('success', "Property Owner '$name' and '$propName' created successfully.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error adding property owner: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/super_admin/owners.php');
    }

    // -------------------------------------------------------------
    // DELETE USER ACCOUNT (SUPER ADMIN ONLY)
    // Supports Property Owners/Admins and Tenant accounts.
    // -------------------------------------------------------------
    if ($action === 'delete_account') {
        $accountId = intval($_POST['account_id'] ?? 0);

        if ($accountId <= 0) {
            setFlash('danger', 'Invalid account selected.');
            redirect(BASE_URL . 'views/super_admin/owners.php');
        }

        // Never allow the currently logged-in Super Admin or another
        // Super Admin account to be deleted from this screen.
        try {
            $stmt = $pdo->prepare("SELECT id, name, username, email, role FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$accountId]);
            $account = $stmt->fetch();

            if (!$account) {
                throw new Exception('Account not found.');
            }

            if ($account['role'] === 'super_admin') {
                throw new Exception('Super Administrator accounts cannot be deleted from User Management.');
            }

            if ($accountId === intval($_SESSION['user_id'] ?? 0)) {
                throw new Exception('You cannot delete your own account.');
            }

            $pdo->beginTransaction();

            // Deleting an admin cascades to properties/units according to the
            // database foreign keys. Tenant user records are detached from
            // the tenant profile via ON DELETE SET NULL.
            $del = $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('admin', 'tenant')");
            $del->execute([$accountId]);

            if ($del->rowCount() !== 1) {
                throw new Exception('The account could not be deleted.');
            }

            $pdo->commit();

            $roleLabel = $account['role'] === 'admin' ? 'Property Owner' : 'Tenant';
            logActivity(
                'ACCOUNT_DELETED',
                "Deleted {$roleLabel} account: {$account['name']} ({$account['username']}, {$account['email']})."
            );
            setFlash('success', "{$roleLabel} account '{$account['name']}' was permanently deleted.");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', 'Unable to delete account: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/super_admin/owners.php');
    }

    // -------------------------------------------------------------
    // UPDATE SYSTEM SETTINGS
    // -------------------------------------------------------------
    if ($action === 'update_settings') {
        $settings = [
            'system_name'          => trim($_POST['system_name'] ?? 'ResiPro Apartment Management'),
            'currency_symbol'      => trim($_POST['currency_symbol'] ?? '₱'),
            'company_email'        => trim($_POST['company_email'] ?? ''),
            'company_phone'        => trim($_POST['company_phone'] ?? ''),
            'company_address'      => trim($_POST['company_address'] ?? ''),
            'default_water_rate'   => floatval($_POST['default_water_rate'] ?? 45.00),
            'default_electric_rate'=> floatval($_POST['default_electric_rate'] ?? 14.50),
            'default_penalty_rate' => floatval($_POST['default_penalty_rate'] ?? 250.00),
            'payment_gcash_name'   => trim($_POST['payment_gcash_name'] ?? ''),
            'payment_gcash_number' => trim($_POST['payment_gcash_number'] ?? ''),
            'payment_bank_info'    => trim($_POST['payment_bank_info'] ?? '')
        ];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($settings as $key => $val) {
                $stmt->execute([$key, $val]);
            }

            $rateStmt = $pdo->prepare("UPDATE units SET water_rate_per_unit = ?, electric_rate_per_kwh = ?");
            $rateStmt->execute([
                $settings['default_water_rate'],
                $settings['default_electric_rate']
            ]);

            $pdo->commit();

            logActivity('SETTINGS_UPDATED', 'Updated global system settings and synchronized utility rates to all units.');
            setFlash('success', 'System settings saved successfully. Water and power rates have been synchronized to all units.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', 'Error updating settings: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/super_admin/system_settings.php');
    }
}

redirect(BASE_URL . 'views/super_admin/dashboard.php');
