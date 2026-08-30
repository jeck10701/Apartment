<?php
/**
 * MaintenanceController - Handles Maintenance Tickets, Work Orders, and Repair Status
 */
require_once dirname(__DIR__) . '/config/config.php';

$pdo = getDBConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // -------------------------------------------------------------
    // CREATE MAINTENANCE TICKET
    // -------------------------------------------------------------
    if ($action === 'create') {
        requireRole(['tenant', 'admin', 'super_admin']);

        $issueTitle  = trim($_POST['issue_title'] ?? '');
        $category    = trim($_POST['category'] ?? 'Plumbing');
        $description = trim($_POST['description'] ?? '');
        $priority    = trim($_POST['priority'] ?? 'medium');
        $unitId      = intval($_POST['unit_id'] ?? 0);
        $tenantId    = intval($_POST['tenant_id'] ?? 0);
        $reqDate     = date('Y-m-d');

        // If submitted from tenant portal, fetch their unit and tenant ID automatically
        if ($_SESSION['user_role'] === 'tenant') {
            $tStmt = $pdo->prepare("SELECT id, unit_id FROM tenants WHERE user_id = ? AND status = 'active' LIMIT 1");
            $tStmt->execute([$_SESSION['user_id']]);
            $t = $tStmt->fetch();
            if ($t) {
                $tenantId = $t['id'];
                $unitId   = $t['unit_id'];
            }
        }

        if (empty($issueTitle) || empty($description) || $unitId <= 0) {
            setFlash('danger', 'Please provide issue title, description, and unit information.');
            $redirectUrl = ($_SESSION['user_role'] === 'tenant') ? BASE_URL . 'views/tenant/maintenance.php' : BASE_URL . 'views/admin/maintenance.php';
            redirect($redirectUrl);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO maintenance_requests (unit_id, tenant_id, issue_title, category, description, priority, status, requested_date) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$unitId, $tenantId, $issueTitle, $category, $description, $priority, $reqDate]);

            logActivity('MAINTENANCE_TICKET_CREATED', "New maintenance ticket: '$issueTitle' for Unit #$unitId.");
            setFlash('success', 'Maintenance request submitted successfully.');
        } catch (Exception $e) {
            setFlash('danger', 'Error submitting maintenance request: ' . $e->getMessage());
        }

        $redirectUrl = ($_SESSION['user_role'] === 'tenant') ? BASE_URL . 'views/tenant/maintenance.php' : BASE_URL . 'views/admin/maintenance.php';
        redirect($redirectUrl);
    }

    // -------------------------------------------------------------
    // UPDATE STATUS & WORK ORDER (ADMIN)
    // -------------------------------------------------------------
    if ($action === 'update_status') {
        requireRole(['admin', 'super_admin']);

        $ticketId   = intval($_POST['ticket_id'] ?? 0);
        $status     = trim($_POST['status'] ?? 'pending');
        $assignedTo = trim($_POST['assigned_to'] ?? '');
        $repairCost = floatval($_POST['repair_cost'] ?? 0);
        $notes      = trim($_POST['notes'] ?? '');
        $resolvedDate = ($status === 'completed') ? date('Y-m-d') : null;

        try {
            $stmt = $pdo->prepare("UPDATE maintenance_requests SET status = ?, assigned_to = ?, repair_cost = ?, notes = ?, resolved_date = ? WHERE id = ?");
            $stmt->execute([$status, $assignedTo, $repairCost, $notes, $resolvedDate, $ticketId]);

            logActivity('MAINTENANCE_UPDATED', "Updated ticket #$ticketId status to '$status'.");
            setFlash('success', 'Maintenance ticket status and work order updated.');
        } catch (Exception $e) {
            setFlash('danger', 'Error updating maintenance ticket: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'views/admin/maintenance.php');
    }
}

redirect(BASE_URL . 'views/admin/maintenance.php');
