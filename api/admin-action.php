<?php
/**
 * INNOWAVE-2K26 — Admin Action API Endpoint
 */
require_once __DIR__ . '/db.php';
session_start();

if (empty($_SESSION['admin_auth'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized admin session. Please log in again.']);
    exit;
}

$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if (!$id || !$action || !isset($pdo)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request parameters.']);
    exit;
}

$nowStr = date('Y-m-d H:i:s');

try {
    if ($action === 'approve_paid') {
        $stmt = $pdo->prepare("UPDATE registrations SET payment_status = 'Paid', paid_at = ? WHERE id = ?");
        $stmt->execute([$nowStr, $id]);
        echo json_encode(['ok' => true, 'msg' => 'Status updated to Approved & Paid.']);
    } else if ($action === 'mark_pending') {
        $stmt = $pdo->prepare("UPDATE registrations SET payment_status = 'Pending Payment Confirmation' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Status updated to Pending.']);
    } else if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM registrations WHERE id = ? OR team_id = ?");
        $stmt->execute([$id, strval($id)]);
        echo json_encode(['ok' => true, 'msg' => 'Registration record deleted directly from database.']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

