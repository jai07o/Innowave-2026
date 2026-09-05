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

$whereClauses = [
    "CAST(id AS CHAR) = :q",
    "LOWER(team_id) = :cleanQ",
    "LOWER(leader_email) = :cleanQ",
    "(LOWER(ieee_id) = :cleanQ AND ieee_id != '')",
    "(payment_ref IS NOT NULL AND LOWER(payment_ref) = :cleanQ)",
    "(roll_no IS NOT NULL AND LOWER(TRIM(roll_no)) = :cleanQ AND roll_no != '')"
];

$params = [
    'q' => $q,
    'cleanQ' => $cleanQ
];

if (!empty($digitsOnly) && strlen($digitsOnly) >= 5) {
    $whereClauses[] = "(REPLACE(REPLACE(leader_phone, '+', ''), ' ', '') LIKE :phone)";
    $params['phone'] = '%' . $digitsOnly . '%';
}

if (strlen($cleanQ) >= 3) {
    $whereClauses[] = "(LOWER(leader_name) LIKE :name)";
    $params['name'] = '%' . $cleanQ . '%';
}

$sql = "SELECT * FROM registrations WHERE " . implode(" OR ", $whereClauses) . " ORDER BY id DESC LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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

$vpa = '1414155000131347@kvbl0001414.ifsc';
$payeeName = 'POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE';
$note = "InnoWave-2k26 {$row['team_id']}";
$upiUri = "upi://pay?pa=" . urlencode($vpa) . "&pn=" . urlencode($payeeName) . "&am={$row['amount']}&cu=INR&tn=" . urlencode($note);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=" . urlencode($upiUri);

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
    'has_payment_screenshot' => (!empty($row['payment_screenshot']) || !empty($row['payment_proof'])),
    'has_payment_ref' => (!empty($row['payment_ref']) && trim($row['payment_ref']) !== ''),
    'amount' => intval($row['amount']),
    'fee_label' => $row['fee_label'],
    'created_at' => $row['created_at'],
    'upi' => [
        'vpa' => $vpa,
        'name' => $payeeName,
        'note' => $note,
        'upiUri' => $upiUri,
        'qr' => $qrUrl,
        'acc_no' => '1414155000131347',
        'ifsc' => 'KVBL0001414',
        'bank' => 'Karur Vysya Bank (KVB)'
    ]
];

echo json_encode(array_merge(['ok' => true, 'found' => true, 'data' => $responseData], $responseData));
