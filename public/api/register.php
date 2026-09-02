<?php
/**
 * INNOWAVE-2K26 — Robust Registration API Endpoint (PHP)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$leader_name   = trim($data['leader_name'] ?? '');
if (empty($leader_name)) $leader_name = 'InnoWave Participant';

$leader_email  = trim($data['leader_email'] ?? '');
if (empty($leader_email)) $leader_email = 'participant_' . time() . '@innowave.com';

$leader_phone  = trim($data['leader_phone'] ?? '');
if (empty($leader_phone)) $leader_phone = '9999999999';

$college_name  = trim($data['college_name'] ?? 'PSCMR CET');
$roll_no       = trim($data['roll_no'] ?? '');
$branch        = trim($data['branch'] ?? 'CSE/ECE/EEE');
$year          = trim($data['year'] ?? '3rd Year');
$ieee_member   = trim($data['ieee_member'] ?? 'No');
$ieee_id       = trim($data['ieee_id'] ?? '');
$ieee_card     = trim($data['ieee_card'] ?? '');
$project_title = trim($data['project_title'] ?? 'INNOWAVE-2K26 Registration');
$track         = trim($data['track'] ?? 'Open Innovation');
$description   = trim($data['description'] ?? '');

$events_selected = '';
if (isset($data['events_selected'])) {
    if (is_array($data['events_selected'])) {
        $events_selected = implode(', ', $data['events_selected']);
    } else {
        $events_selected = trim($data['events_selected']);
    }
}

// Compute Fee:
$isPscmr = false;
$cLower = strtolower($college_name);
if (strpos($cLower, 'pscmr') !== false || strpos($cLower, 'potti sriramulu') !== false || strpos($cLower, 'chalavadi') !== false || strpos($cLower, 'mallikarjuna rao') !== false) {
    $isPscmr = true;
}

$amount = $isPscmr ? 50 : 100;
$fee_label = $isPscmr ? 'PSCMR Student Special Discount (₹50)' : 'Standard Delegate Fee (₹100)';
$isIeee = ($ieee_member === 'Yes');
$ieeeStatus = $isIeee ? 'Card Approved' : 'N/A';
$initialPaymentStatus = 'Pending Payment Confirmation';
$createdAt = date('Y-m-d H:i:s');

// Generate new sequential Team ID (IW26-XXXX)
$maxStmt = $pdo->query("SELECT MAX(reg_seq) as max_seq FROM registrations");
$maxRow = $maxStmt ? $maxStmt->fetch() : null;
$nextSeq = ($maxRow && !empty($maxRow['max_seq'])) ? intval($maxRow['max_seq']) + 1 : 1;
$team_id = 'IW26-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

$insertStmt = $pdo->prepare("
    INSERT INTO registrations (
        team_id, reg_seq, project_title, track, events_selected, description,
        leader_name, leader_email, leader_phone, college_name, roll_no, branch, year,
        ieee_member, ieee_id, ieee_card, ieee_verification_status, team_size,
        amount, fee_label, payment_mode, payment_status, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, 1,
        ?, ?, 'Bank Transfer', ?, ?
    )
");

$insertStmt->execute([
    $team_id, $nextSeq, $project_title, $track, $events_selected, $description,
    $leader_name, $leader_email, $leader_phone, $college_name, $roll_no, $branch, $year,
    $ieee_member, $ieee_id, $ieee_card, $ieeeStatus,
    $amount, $fee_label, $initialPaymentStatus, $createdAt
]);

$regId = $pdo->lastInsertId();

echo json_encode([
    'ok' => true,
    'id' => intval($regId),
    'team_id' => $team_id,
    'amount' => $amount,
    'fee_label' => $fee_label,
    'ieee_verification_status' => $ieeeStatus,
    'upi' => [
        'vpa' => '6309419599@axl',
        'payee' => 'POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE'
    ]
]);
