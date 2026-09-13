<?php
/**
 * INNOWAVE-2K26 — Robust Payment UTR Submission API Endpoint (PHP + MySQL)
 */
if (!ob_get_level()) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        @ob_start('ob_gzhandler');
    } else {
        @ob_start();
    }
}
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'MySQL Database is offline: ' . ($lastMysqlError ?: 'Please verify MySQL database credentials in api/config.php.')
    ]);
    exit;
}

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
    if ($row && !empty($data['leader_email'])) {
        $checkEmail = strtolower(trim($data['leader_email']));
        if (strtolower(trim($row['leader_email'])) !== $checkEmail) {
            // regId does not belong to this email, reset row to look up by email
            $row = null;
        }
    }
}

if (!$row && !empty($data['team_id'])) {
    $teamId = trim($data['team_id']);
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE LOWER(TRIM(team_id)) = LOWER(TRIM(?)) LIMIT 1");
    $stmt->execute([$teamId]);
    $row = $stmt->fetch();
}

if (!$row && !empty($data['leader_email'])) {
    $cleanEmail = strtolower(trim($data['leader_email']));
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE LOWER(TRIM(leader_email)) = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$cleanEmail]);
    $row = $stmt->fetch();
}

if (!$row && !empty($data['leader_phone'])) {
    $cleanPhone = preg_replace('/\D/', '', $data['leader_phone']);
    if (strlen($cleanPhone) >= 10) {
        $stmt = $pdo->prepare("SELECT * FROM registrations WHERE REPLACE(REPLACE(leader_phone, '+', ''), ' ', '') LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(['%' . substr($cleanPhone, -10)]);
        $row = $stmt->fetch();
    }
}

// Strictly prevent overwriting/cloning: NEVER arbitrarily pick the latest record
if (!$row) {
    echo json_encode([
        'ok' => false,
        'errors' => ['Registration record not found. Please ensure you have registered first or check your status on the registration page.']
    ]);
    exit;
}

$regId = intval($row['id']);

$cleanRef = trim(preg_replace('/\s+/', '', $utrRef));
$digitsOnly = preg_replace('/\D/', '', $cleanRef);
if (strlen($digitsOnly) === 12) {
    $cleanRef = $digitsOnly;
}

if (empty($cleanRef) || strlen($cleanRef) < 6) {
    echo json_encode(['ok' => false, 'errors' => ['Please enter a valid 12-digit UPI transaction / reference ID (UTR).']]);
    exit;
}

if (empty($screenshotBase64)) {
    echo json_encode(['ok' => false, 'errors' => ['Please upload your payment screenshot.']]);
    exit;
}

$curStatus = strtolower(trim($row['payment_status'] ?? ''));
if ($curStatus === 'paid' || $curStatus === 'confirmed' || $curStatus === 'approved') {
    echo json_encode(['ok' => false, 'errors' => ['This registration is already verified & confirmed.']]);
    exit;
}

// 🚫 BLOCK SUBMISSION: Duplicate UTR Check in MySQL Database
$dupStmt = $pdo->prepare("
    SELECT id, team_id, leader_name, payment_status
    FROM registrations
    WHERE payment_ref IS NOT NULL
      AND TRIM(payment_ref) != ''
      AND UPPER(REPLACE(TRIM(payment_ref), ' ', '')) = UPPER(TRIM(?))
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

// Determine and verify expected amount strictly based on participant details:
// PSCMR IEEE = ₹50 | PSCMR Non-IEEE = ₹100
// Other College IEEE = ₹100 | Other College Non-IEEE = ₹200
$cClean = strtolower(preg_replace('/[^a-z0-9]/', '', $row['college_name'] ?? ''));
$isPscmr = (
    strpos($cClean, 'pscmr') !== false ||
    strpos($cClean, 'pottisriramulu') !== false ||
    strpos($cClean, 'pottisreeramulu') !== false ||
    strpos($cClean, 'chalavadi') !== false ||
    strpos($cClean, 'mallikarjuna') !== false
);

$isIeee = (($row['ieee_member'] ?? '') === 'Yes');

if ($isPscmr) {
    $expectedAmt = $isIeee ? 50 : 100;
    $computedLabel = $isIeee
        ? 'PSCMR CET IEEE Member Delegate Fee: ₹50'
        : 'PSCMR CET Non-IEEE Student Delegate Fee: ₹100';
} else {
    $expectedAmt = $isIeee ? 100 : 200;
    $computedLabel = $isIeee
        ? 'Other College IEEE Member Delegate Fee: ₹100'
        : 'Other College Non-IEEE Student Delegate Fee: ₹200';
}

// Amount strictly takes ONLY 50, 100, or 200 according to participant details:
$currentAmt = $expectedAmt;
$feeLabel = $computedLabel;

// 🚫 STRICT BACKEND GUARDRAIL: Block submission if uploaded screenshot detected amount mismatches required fee!
if (!empty($data['detected_amount'])) {
    $clientDetectedAmt = intval($data['detected_amount']);
    if ($clientDetectedAmt > 0 && $clientDetectedAmt !== $expectedAmt) {
        echo json_encode([
            'ok' => false,
            'errors' => [
                "🚫 PAYMENT AMOUNT MISMATCH:\n\n• Your required registration fee is ₹{$expectedAmt} ({$computedLabel}).\n• The uploaded screenshot shows a payment of ₹" . number_format($clientDetectedAmt) . ".\n\nPlease upload the genuine payment receipt showing your ₹{$expectedAmt} transfer."
            ]
        ]);
        exit;
    }
}

if (!empty($utrWarning) && stripos($utrWarning, 'AMOUNT MISMATCH') !== false) {
    echo json_encode([
        'ok' => false,
        'errors' => [$utrWarning]
    ]);
    exit;
}

$paidAt = date('Y-m-d H:i:s');
$paymentStatus = 'Pending Payment Confirmation';
$paymentMode = trim($data['payment_mode'] ?? $data['payment_app'] ?? 'UPI Transfer');

$updateStmt = $pdo->prepare("
    UPDATE registrations SET
        payment_ref = ?,
        payment_screenshot = ?,
        payment_proof = ?,
        paid_at = ?,
        payment_status = ?,
        amount = ?,
        fee_label = ?,
        payment_mode = ?,
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
    $currentAmt,
    $feeLabel,
    $paymentMode,
    $utrMismatch,
    $utrWarning,
    $regId
]);

$category = ($isPscmr ? 'PSCMR CET' : 'Other College') . ' ' . ($isIeee ? 'IEEE Member' : 'Non-IEEE Student');

echo json_encode([
    'ok' => true,
    'id' => $regId,
    'team_id' => $row['team_id'],
    'amount' => $currentAmt,
    'fee_label' => $feeLabel,
    'category' => $category,
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
