<?php
/**
 * INNOWAVE-2K26 — Payment UTR Submission API Endpoint (PHP + SQLite)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method. Only POST is supported.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$regId           = intval($data['regId'] ?? $data['id'] ?? 0);
$utrRef          = trim($data['utrRef'] ?? $data['utr'] ?? '');
$screenshotBase64= trim($data['screenshotBase64'] ?? $data['screenshot'] ?? '');

if (!$regId && isset($data['team_id'])) {
    $teamId = trim($data['team_id']);
    $stmt = $pdo->prepare("SELECT id FROM registrations WHERE team_id = ? LIMIT 1");
    $stmt->execute([$teamId]);
    $r = $stmt->fetch();
    if ($r) $regId = intval($r['id']);
}

if (!$regId) {
    echo json_encode(['ok' => false, 'error' => 'Registration ID is required.']);
    exit;
}

if (empty($utrRef)) {
    echo json_encode(['ok' => false, 'error' => '12-digit UTR Transaction Reference Number is required.']);
    exit;
}

$cleanUtr = preg_replace('/\D/', '', $utrRef);

$stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
$stmt->execute([$regId]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Registration record not found.']);
    exit;
}

$paidAt = date('Y-m-d H:i:s');
$status = 'Pending Verification';

$updateStmt = $pdo->prepare("
    UPDATE registrations SET
        payment_ref = ?,
        payment_proof = ?,
        payment_status = ?,
        paid_at = ?
    WHERE id = ?
");

$updateStmt->execute([
    $cleanUtr,
    $screenshotBase64,
    $status,
    $paidAt,
    $regId
]);

echo json_encode([
    'ok' => true,
    'id' => $regId,
    'team_id' => $row['team_id'],
    'leader_name' => $row['leader_name'],
    'leader_email' => $row['leader_email'],
    'amount' => intval($row['amount']),
    'payment_status' => $status,
    'payment_ref' => $cleanUtr
]);
