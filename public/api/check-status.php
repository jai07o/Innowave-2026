<?php
/**
 * INNOWAVE-2K26 — Registration & Payment Status Check API Endpoint (PHP + MySQL)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? $_GET['id'] ?? '');

if (empty($q)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter your Registration ID, Mobile, or Email.']);
    exit;
}

$cleanQ = strtolower($q);
$digitsOnly = preg_replace('/\D/', '', $q);

// Search by Registration ID, Team ID, Phone, Email, IEEE ID, Name, or UTR Reference
$stmt = $pdo->prepare("
    SELECT *
    FROM registrations
    WHERE CAST(id AS CHAR) = ?
       OR LOWER(team_id) = ?
       OR LOWER(leader_email) = ?
       OR (LOWER(ieee_id) = ? AND ieee_id != '')
       OR (leader_phone LIKE ? AND CHAR_LENGTH(?) >= 6)
       OR (LOWER(leader_name) LIKE ? AND CHAR_LENGTH(?) >= 3)
       OR (payment_ref IS NOT NULL AND LOWER(payment_ref) = ?)
    ORDER BY id DESC LIMIT 1
");

$phoneSearch = '%' . $digitsOnly . '%';
$nameSearch  = '%' . $cleanQ . '%';

$stmt->execute([
    $q,
    $cleanQ,
    $cleanQ,
    $cleanQ,
    $phoneSearch, $digitsOnly,
    $nameSearch, $cleanQ,
    $cleanQ
]);

$row = $stmt->fetch();

if (!$row) {
    echo json_encode([
        'ok' => false,
        'found' => false,
        'error' => 'Registration record not found. Please check your Registration ID, Mobile, Email, IEEE ID, or UTR Reference.'
    ]);
    exit;
}

$isIeee = ($row['ieee_member'] === 'Yes');
$ieeeStatus = $row['ieee_verification_status'] ?: ($isIeee ? 'Card Approved' : 'N/A');
$paymentStatus = $row['payment_status'] ?: 'Pending Payment Confirmation';

$vpa = '6309419599@axl';
$payeeName = 'PSCMR IEEE Student Branch';
$note = "InnoWave-2k26 {$row['team_id']}";
$upiUri = "upi://pay?pa=" . urlencode($vpa) . "&pn=" . urlencode($payeeName) . "&am={$row['amount']}&cu=INR&tn=" . urlencode($note);

$responseData = [
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
    'ieee_verification_status' => $ieeeStatus,
    'payment_status' => $paymentStatus,
    'payment_ref' => $row['payment_ref'],
    'amount' => intval($row['amount']),
    'fee_label' => $row['fee_label'],
    'created_at' => $row['created_at'],
    'upi' => [
        'vpa' => $vpa,
        'name' => $payeeName,
        'note' => $note,
        'upiUri' => $upiUri
    ]
];

echo json_encode(array_merge(['ok' => true, 'found' => true, 'data' => $responseData], $responseData));
