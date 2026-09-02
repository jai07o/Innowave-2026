<?php
/**
 * INNOWAVE-2K26 — Admin Action API Endpoint
 */
require_once __DIR__ . '/db.php';
session_start();

if (empty($_SESSION['admin_auth'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized admin session.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if (!$id || !$action || !isset($pdo)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request parameters.']);
    exit;
}

try {
    if ($action === 'approve_paid') {
        $stmt = $pdo->prepare("UPDATE registrations SET payment_status = 'Paid', paid_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Status updated to Paid & Verified.']);
    } else if ($action === 'mark_pending') {
        $stmt = $pdo->prepare("UPDATE registrations SET payment_status = 'Pending Payment Confirmation' WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Status updated to Pending.']);
    } else if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM registrations WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Registration record deleted.']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
