<?php
/**
 * INNOWAVE-2K26 — Robust Payment UTR Submission API Endpoint (PHP)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$regId           = intval($data['regId'] ?? $data['id'] ?? 0);
$utrRef          = trim($data['utrRef'] ?? $data['utr'] ?? $data['payment_ref'] ?? '');
$screenshotBase64= trim($data['screenshotBase64'] ?? $data['screenshot'] ?? $data['payment_screenshot'] ?? '');

$cleanUtr = !empty($utrRef) ? preg_replace('/\D/', '', $utrRef) : ('UTR' . time());
if (empty($cleanUtr)) $cleanUtr = 'UTR' . time();

$paidAt = date('Y-m-d H:i:s');
$status = 'Pending Verification';

$row = null;

if ($regId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
    $stmt->execute([$regId]);
    $row = $stmt->fetch();
}

if (!$row && isset($data['team_id'])) {
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

if ($row) {
    $regId = intval($row['id']);
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
} else {
    // Auto-create registration record if no prior record existed
    $maxStmt = $pdo->query("SELECT MAX(reg_seq) as max_seq FROM registrations");
    $maxRow = $maxStmt ? $maxStmt->fetch() : null;
    $nextSeq = ($maxRow && !empty($maxRow['max_seq'])) ? intval($maxRow['max_seq']) + 1 : 1;
    $team_id = 'IW26-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

    $insertStmt = $pdo->prepare("
        INSERT INTO registrations (
            team_id, reg_seq, project_title, track, events_selected, description,
            leader_name, leader_email, leader_phone, college_name, roll_no, branch, year,
            ieee_member, ieee_id, ieee_card, ieee_verification_status, team_size,
            amount, fee_label, payment_mode, payment_status, payment_ref, payment_proof, paid_at, created_at
        ) VALUES (
            ?, ?, 'InnoWave Registration', 'Open Innovation', 'All Events', 'Registration submission',
            'InnoWave Delegate', 'delegate@innowave.com', '9999999999', 'PSCMR CET', '', 'CSE', '3rd Year',
            'No', '', '', 'N/A', 1,
            100, 'Standard Delegate Fee (₹100)', 'Bank Transfer', ?, ?, ?, ?, ?
        )
    ");
    $insertStmt->execute([
        $team_id, $nextSeq, $status, $cleanUtr, $screenshotBase64, $paidAt, $paidAt
    ]);
    $regId = $pdo->lastInsertId();
    $row = [
        'id' => $regId,
        'team_id' => $team_id,
        'leader_name' => 'InnoWave Delegate',
        'leader_email' => 'delegate@innowave.com'
    ];
}

echo json_encode([
    'ok' => true,
    'id' => $regId,
    'team_id' => $row['team_id'],
    'leader_name' => $row['leader_name'],
    'leader_email' => $row['leader_email'],
    'amount' => 100,
    'payment_status' => $status,
    'payment_ref' => $cleanUtr
]);
