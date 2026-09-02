<?php
/**
 * INNOWAVE-2K26 — Registration & Payment Status Check API Endpoint (PHP + SQLite)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if (empty($q)) {
    echo json_encode(['ok' => false, 'error' => 'Query search parameter is required.']);
    exit;
}

$cleanQ = strtolower($q);
$digitsOnly = preg_replace('/\D/', '', $q);

// Multi-credential search
$stmt = $pdo->prepare("
    SELECT id, team_id, leader_name, leader_email, leader_phone, college_name, branch, year,
           ieee_member, ieee_id, amount, fee_label, payment_status, payment_ref, ieee_verification_status, created_at
    FROM registrations
    WHERE LOWER(team_id) = ?
       OR LOWER(leader_email) = ?
       OR (LOWER(ieee_id) = ? AND ieee_id != '')
       OR (leader_phone LIKE ? AND length(?) >= 6)
       OR (LOWER(leader_name) LIKE ? AND length(?) >= 3)
       OR (payment_ref = ? AND payment_ref != '')
    ORDER BY id DESC LIMIT 1
");

$phoneSearch = '%' . $digitsOnly . '%';
$nameSearch  = '%' . $cleanQ . '%';

$stmt->execute([
    $cleanQ,
    $cleanQ,
    $cleanQ,
    $phoneSearch, $digitsOnly,
    $nameSearch, $cleanQ,
    $q
]);

$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'found' => false, 'error' => 'No registration record found matching details.']);
    exit;
}

$paymentStatus = $row['payment_status'] ?? 'Pending Payment Confirmation';
if (!empty($row['payment_ref']) && ($paymentStatus === 'Pending Payment Confirmation' || $paymentStatus === 'Pending Verification')) {
    $paymentStatus = 'Pending Verification';
}

echo json_encode([
    'ok' => true,
    'found' => true,
    'data' => [
        'id' => intval($row['id']),
        'team_id' => $row['team_id'],
        'leader_name' => $row['leader_name'],
        'leader_email' => $row['leader_email'],
        'leader_phone' => $row['leader_phone'],
        'college_name' => $row['college_name'],
        'branch' => $row['branch'],
        'year' => $row['year'],
        'ieee_member' => $row['ieee_member'],
        'ieee_id' => $row['ieee_id'],
        'amount' => intval($row['amount']),
        'fee_label' => $row['fee_label'],
        'payment_status' => ($paymentStatus === 'Paid' || $paymentStatus === 'Confirmed') ? 'Paid' : 'Pending',
        'payment_ref' => $row['payment_ref'],
        'ieee_verification_status' => $row['ieee_verification_status'],
        'created_at' => $row['created_at'],
        'upi' => [
            'vpa' => '6309419599@axl',
            'payee' => 'POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE'
        ]
    ]
]);
