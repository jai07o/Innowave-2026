<?php
/**
 * INNOWAVE-2K26 — Participant ID Card & Ticket Data API Endpoint (PHP + MySQL)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$q = trim($_GET['id'] ?? $_GET['q'] ?? '');

if ($q === 'all' || (!empty($_GET['all']) && $_GET['all'] === 'true')) {
    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id ASC");
    $rows = $stmt ? $stmt->fetchAll() : [];
    echo json_encode(['ok' => true, 'count' => count($rows), 'participants' => $rows]);
    exit;
}

if (empty($q)) {
    echo json_encode(['ok' => false, 'error' => 'Participant ID or Registration ID required.']);
    exit;
}

$cleanQ = strtolower($q);

$stmt = $pdo->prepare("
    SELECT *
    FROM registrations
    WHERE CAST(id AS CHAR) = ?
       OR LOWER(team_id) = ?
       OR leader_phone = ?
       OR LOWER(leader_email) = ?
    ORDER BY id DESC LIMIT 1
");

$stmt->execute([$q, $cleanQ, $q, $cleanQ]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Participant record not found in Admin database.']);
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
