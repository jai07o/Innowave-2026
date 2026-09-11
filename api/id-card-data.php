<?php
/**
 * INNOWAVE-2K26 — Participant ID Card & Ticket Data API Endpoint (PHP + MySQL)
 */
if (!ob_get_level()) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        @ob_start('ob_gzhandler');
    } else {
        @ob_start();
    }
}
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'MySQL Database is offline: ' . ($lastMysqlError ?: 'Please verify MySQL database credentials in api/config.php.')
    ]);
    exit;
}

$q = trim($_GET['id'] ?? $_GET['q'] ?? '');

if ($q === 'all' || (!empty($_GET['all']) && $_GET['all'] === 'true')) {
    $cols = "id, team_id, reg_seq, project_title, track, events_selected, leader_name, leader_email, leader_phone, college_name, roll_no, branch, year, ieee_member, ieee_id, ieee_verification_status, ieee_email, ieee_grade, ieee_count, non_ieee_count, team_size, member2, member3, member4, amount, fee_label, payment_mode, payment_status, payment_ref, duplicate_utr, utr_mismatch, paid_at, created_at";
    $stmt = $pdo->query("SELECT {$cols} FROM registrations ORDER BY id ASC");
    $rows = $stmt ? $stmt->fetchAll() : [];
    echo json_encode(['ok' => true, 'count' => count($rows), 'participants' => $rows]);
    exit;
}

if (empty($q)) {
    echo json_encode(['ok' => false, 'error' => 'Participant ID or Registration ID required.']);
    exit;
}

$cleanQ = strtolower($q);
$numVal = intval(preg_replace('/\D/', '', $q));

$stmt = $pdo->prepare("
    SELECT *
    FROM registrations
    WHERE id = ?
       OR reg_seq = ?
       OR LOWER(TRIM(team_id)) = ?
       OR REPLACE(LOWER(team_id), ' ', '') = ?
       OR leader_phone LIKE ?
       OR LOWER(TRIM(leader_email)) = ?
    ORDER BY id DESC LIMIT 1
");

$stmt->execute([
    $numVal ?: -1,
    $numVal ?: -1,
    $cleanQ,
    str_replace(' ', '', $cleanQ),
    '%' . $q . '%',
    $cleanQ
]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Participant record not found. Please verify your Registration ID or Mobile number.']);
    exit;
}

$eventsSelected = [];
if (!empty($row['events_selected'])) {
    $decoded = json_decode($row['events_selected'], true);
    if (is_array($decoded)) {
        $eventsSelected = $decoded;
    } else {
        $eventsSelected = array_filter(array_map('trim', explode(',', $row['events_selected'])));
    }
}

echo json_encode([
    'ok' => true,
    'participant' => [
        'id' => intval($row['id']),
        'team_id' => $row['team_id'] ?: ('IW26-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)),
        'leader_name' => $row['leader_name'],
        'leader_phone' => $row['leader_phone'] ?: 'N/A',
        'leader_email' => $row['leader_email'] ?: 'N/A',
        'roll_no' => $row['roll_no'] ?: 'N/A',
        'branch' => $row['branch'] ?: 'N/A',
        'year' => $row['year'] ?: 'N/A',
        'college_name' => $row['college_name'] ?: 'PSCMR College of Engineering & Technology',
        'track' => $row['track'] ?: 'Open Innovation',
        'project_title' => $row['project_title'] ?: 'InnoWave Participant',
        'ieee_member' => $row['ieee_member'] ?: 'No',
        'ieee_id' => $row['ieee_id'] ?: '',
        'payment_status' => $row['payment_status'] ?: 'Paid',
        'payment_ref' => $row['payment_ref'] ?: '',
        'events_selected' => $eventsSelected
    ]
]);
