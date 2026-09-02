<?php
/**
 * INNOWAVE-2K26 — Admin Portal API Endpoint (PHP + SQLite)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pass   = $_GET['password'] ?? $_POST['password'] ?? $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';

$ADMIN_PASSWORD = 'innowave2k26';

if ($action === 'login') {
    if ($pass === $ADMIN_PASSWORD) {
        echo json_encode(['ok' => true, 'message' => 'Admin authenticated successfully.']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid admin password.']);
    }
    exit;
}

if ($pass !== $ADMIN_PASSWORD) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized admin access. Invalid password.']);
    exit;
}

if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id DESC");
    $rows = $stmt->fetchAll();

    $totalRegs = count($rows);
    $ieeeCount = 0;
    $nonIeeeCount = 0;
    $totalAmount = 0;
    $pendingIeeeCount = 0;

    foreach ($rows as $r) {
        if ($r['ieee_member'] === 'Yes') {
            $ieeeCount++;
            if ($r['ieee_verification_status'] === 'Pending Card Verification') {
                $pendingIeeeCount++;
            }
        } else {
            $nonIeeeCount++;
        }
        if ($r['payment_status'] === 'Paid' || $r['payment_status'] === 'Confirmed') {
            $totalAmount += intval($r['amount']);
        }
    }

    echo json_encode([
        'ok' => true,
        'stats' => [
            'total' => $totalRegs,
            'ieee' => $ieeeCount,
            'non_ieee' => $nonIeeeCount,
            'amount' => $totalAmount,
            'pending_ieee' => $pendingIeeeCount
        ],
        'data' => $rows
    ]);
    exit;
}

if ($action === 'approve-ieee') {
    $rawInput = file_get_contents('php://input');
    $d = json_decode($rawInput, true) ?: $_POST;
    $id = intval($d['id'] ?? 0);
    $status = trim($d['status'] ?? 'Card Approved');

    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'ID is required.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE registrations SET ieee_verification_status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    echo json_encode(['ok' => true, 'id' => $id, 'status' => $status]);
    exit;
}

if ($action === 'confirm-payment') {
    $rawInput = file_get_contents('php://input');
    $d = json_decode($rawInput, true) ?: $_POST;
    $id = intval($d['id'] ?? 0);
    $status = trim($d['status'] ?? 'Paid');

    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'ID is required.']);
        exit;
    }

    $paidAt = ($status === 'Paid' || $status === 'Confirmed') ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare("UPDATE registrations SET payment_status = ?, paid_at = ? WHERE id = ?");
    $stmt->execute([$status, $paidAt, $id]);

    echo json_encode(['ok' => true, 'id' => $id, 'payment_status' => $status]);
    exit;
}

if ($action === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=innowave_2k26_registrations_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Team ID', 'Participant Name', 'Email', 'Phone', 'College', 'Roll No', 'Branch', 'Year', 'IEEE Member', 'IEEE ID', 'Events Selected', 'Amount', 'Payment Status', 'Payment Ref (UTR)', 'Created At']);

    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id ASC");
    while ($r = $stmt->fetch()) {
        fputcsv($output, [
            $r['id'],
            $r['team_id'],
            $r['leader_name'],
            $r['leader_email'],
            $r['leader_phone'],
            $r['college_name'],
            $r['roll_no'],
            $r['branch'],
            $r['year'],
            $r['ieee_member'],
            $r['ieee_id'],
            $r['events_selected'],
            $r['amount'],
            $r['payment_status'],
            $r['payment_ref'],
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown admin action specified.']);
