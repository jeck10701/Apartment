<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'super_admin']);

$pdo = getDBConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'add') {
        $propertyId = intval($_POST['property_id'] ?? 1);
        $unitNumber = trim($_POST['unit_number'] ?? '');
        $floorLevel = intval($_POST['floor_level'] ?? 1);
        $unitType   = trim($_POST['unit_type'] ?? 'Studio');
        $monthlyRent = floatval($_POST['monthly_rent'] ?? 0);
        $deposit    = floatval($_POST['security_deposit'] ?? $monthlyRent);
        $defaultRates = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('default_water_rate', 'default_electric_rate')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $waterRate  = floatval($_POST['water_rate_per_unit'] ?? ($defaultRates['default_water_rate'] ?? 45.00));
        $elecRate   = floatval($_POST['electric_rate_per_kwh'] ?? ($defaultRates['default_electric_rate'] ?? 14.50));
        $status     = trim($_POST['status'] ?? 'vacant');
        $maxOccupants = intval($_POST['max_occupants'] ?? 2);
        $description = trim($_POST['description'] ?? '');

        if (empty($unitNumber) || $monthlyRent <= 0) {
            setFlash('danger', 'Unit number and valid monthly rent are required.');
            redirect(BASE_URL . 'views/admin/units.php');
        }

        try {

            $check = $pdo->prepare("SELECT id FROM units WHERE property_id = ? AND unit_number = ?");
            $check->execute([$propertyId, $unitNumber]);
            if ($check->fetch()) {
                setFlash('danger', "Unit '$unitNumber' already exists for this property.");
                redirect(BASE_URL . 'views/admin/units.php');
            }

            $stmt = $pdo->prepare("INSERT INTO units (property_id, unit_number, floor_level, unit_type, monthly_rent, security_deposit, water_rate_per_unit, electric_rate_per_kwh, status, max_occupants, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$propertyId, $unitNumber, $floorLevel, $unitType, $monthlyRent, $deposit, $waterRate, $elecRate, $status, $maxOccupants, $description]);

            logActivity('UNIT_ADDED', "Added new unit $unitNumber ($unitType, ₱$monthlyRent/mo).");
            setFlash('success', "Unit '$unitNumber' has been successfully created.");
        } catch (Exception $e) {
            setFlash('danger', 'Error adding unit: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/units.php');
    }

    if ($action === 'edit') {
        $unitId      = intval($_POST['unit_id'] ?? 0);
        $unitNumber  = trim($_POST['unit_number'] ?? '');
        $floorLevel  = intval($_POST['floor_level'] ?? 1);
        $unitType    = trim($_POST['unit_type'] ?? 'Studio');
        $monthlyRent = floatval($_POST['monthly_rent'] ?? 0);
        $deposit     = floatval($_POST['security_deposit'] ?? 0);
        $defaultRates = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('default_water_rate', 'default_electric_rate')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $waterRate   = floatval($_POST['water_rate_per_unit'] ?? ($defaultRates['default_water_rate'] ?? 45.00));
        $elecRate    = floatval($_POST['electric_rate_per_kwh'] ?? ($defaultRates['default_electric_rate'] ?? 14.50));
        $status      = trim($_POST['status'] ?? 'vacant');
        $maxOccupants = intval($_POST['max_occupants'] ?? 2);
        $description = trim($_POST['description'] ?? '');

        if ($unitId <= 0 || empty($unitNumber) || $monthlyRent <= 0) {
            setFlash('danger', 'Invalid unit update parameters.');
            redirect(BASE_URL . 'views/admin/units.php');
        }

        try {
            $stmt = $pdo->prepare("UPDATE units SET unit_number = ?, floor_level = ?, unit_type = ?, monthly_rent = ?, security_deposit = ?, water_rate_per_unit = ?, electric_rate_per_kwh = ?, status = ?, max_occupants = ?, description = ? WHERE id = ?");
            $stmt->execute([$unitNumber, $floorLevel, $unitType, $monthlyRent, $deposit, $waterRate, $elecRate, $status, $maxOccupants, $description, $unitId]);

            logActivity('UNIT_UPDATED', "Updated unit details for $unitNumber (ID: $unitId).");
            setFlash('success', "Unit '$unitNumber' details updated successfully.");
        } catch (Exception $e) {
            setFlash('danger', 'Error updating unit: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/units.php');
    }

    if ($action === 'delete') {
        $unitId = intval($_POST['unit_id'] ?? 0);

        try {

            $check = $pdo->prepare("SELECT id FROM tenants WHERE unit_id = ? AND status = 'active'");
            $check->execute([$unitId]);
            if ($check->fetch()) {
                setFlash('danger', 'Cannot delete this unit because it is currently assigned to an active tenant.');
                redirect(BASE_URL . 'views/admin/units.php');
            }

            $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
            $stmt->execute([$unitId]);

            logActivity('UNIT_DELETED', "Deleted unit record ID: $unitId.");
            setFlash('success', 'Unit has been successfully removed.');
        } catch (Exception $e) {
            setFlash('danger', 'Error deleting unit: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/units.php');
    }
}

redirect(BASE_URL . 'views/admin/units.php');
