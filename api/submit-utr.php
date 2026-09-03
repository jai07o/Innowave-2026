<?php
/**
 * INNOWAVE-2K26 — Robust Payment UTR Submission API Endpoint (PHP + MySQL)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$regId            = intval($data['regId'] ?? $data['id'] ?? 0);
$utrRef           = trim($data['payment_ref'] ?? $data['utrRef'] ?? $data['utr'] ?? '');
$screenshotBase64 = trim($data['payment_screenshot'] ?? $data['screenshotBase64'] ?? $data['screenshot'] ?? '');
$utrMismatch      = !empty($data['utr_mismatch']) ? 1 : 0;
$utrWarning       = !empty($data['utr_warning']) ? trim($data['utr_warning']) : null;

$row = null;

if ($regId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
    $stmt->execute([$regId]);
    $row = $stmt->fetch();
}

if (!$row && !empty($data['team_id'])) {
    $teamId = trim($data['team_id']);
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE team_id = ? LIMIT 1");
    $stmt->execute([$teamId]);
    $row = $stmt->fetch();
}

if (!$row) {
    // Grab latest submitted registration if ID not explicitly provided
    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id DESC LIMIT 1");
    $row = $stmt ? $stmt->fetch() : null;
}

if (!$row) {
    echo json_encode(['ok' => false, 'errors' => ['Registration not found. Please start your registration again.']]);
    exit;
}

$regId = intval($row['id']);

if (empty($utrRef) || strlen(preg_replace('/\s+/', '', $utrRef)) < 6) {
    echo json_encode(['ok' => false, 'errors' => ['Please enter a valid 12-digit UPI transaction / reference ID (UTR).']]);
    exit;
}

if (empty($screenshotBase64)) {
    echo json_encode(['ok' => false, 'errors' => ['Please upload your payment screenshot.']]);
    exit;
}

if ($row['payment_status'] === 'Paid') {
    echo json_encode(['ok' => false, 'errors' => ['This registration is already verified & confirmed.']]);
    exit;
}

$cleanRef = preg_replace('/\s+/', '', $utrRef);

// 🚫 BLOCK SUBMISSION: Duplicate UTR Check in MySQL Database
$dupStmt = $pdo->prepare("
    SELECT id, team_id, leader_name, payment_status
    FROM registrations
    WHERE payment_ref IS NOT NULL
      AND TRIM(payment_ref) != ''
      AND UPPER(TRIM(payment_ref)) = UPPER(TRIM(?))
      AND id != ?
    LIMIT 1
");
$dupStmt->execute([$cleanRef, $regId]);
$existingUtrRow = $dupStmt->fetch();

if ($existingUtrRow) {
    echo json_encode([
        'ok' => false,
        'errors' => [
            "🚫 DUPLICATE UTR DETECTED:\n\nThe UTR / Reference ID '{$cleanRef}' has ALREADY been submitted by another participant ({$existingUtrRow['team_id']}).\n\nPlease check your transaction receipt and enter your own valid 12-digit UPI reference ID."
        ]
    ]);
    exit;
}

$paidAt = date('Y-m-d H:i:s');
$paymentStatus = 'Pending Payment Confirmation';

$updateStmt = $pdo->prepare("
    UPDATE registrations SET
        payment_ref = ?,
        payment_screenshot = ?,
        payment_proof = ?,
        paid_at = ?,
        payment_status = ?,
        duplicate_utr = 0,
        utr_mismatch = ?,
        utr_warning = ?
    WHERE id = ?
");

$updateStmt->execute([
    $cleanRef,
    $screenshotBase64,
    $screenshotBase64,
    $paidAt,
    $paymentStatus,
    $utrMismatch,
    $utrWarning,
    $regId
]);

echo json_encode([
    'ok' => true,
    'id' => $regId,
    'team_id' => $row['team_id'],
    'amount' => intval($row['amount']),
    'fee_label' => $row['fee_label'],
    'payment_ref' => $cleanRef,
    'payment_status' => $paymentStatus,
    'auto_verified' => true,
    'project_title' => $row['project_title'],
    'track' => $row['track'],
    'leader_name' => $row['leader_name'],
    'leader_email' => $row['leader_email'],
    'leader_phone' => $row['leader_phone'],
    'college_name' => $row['college_name'],
    'ieee_member' => $row['ieee_member'],
    'team_size' => intval($row['team_size'])
]);
