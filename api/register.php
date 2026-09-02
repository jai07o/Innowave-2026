<?php
/**
 * INNOWAVE-2K26 — Robust Registration API Endpoint (PHP + MySQL)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$errors = [];
$existingId = !empty($data['existing_id']) ? intval($data['existing_id']) : null;

// Parse Selected Events
$events_selected = [];
if (isset($data['events_selected'])) {
    if (is_array($data['events_selected'])) {
        $events_selected = $data['events_selected'];
    } else if (is_string($data['events_selected'])) {
        $decoded = json_decode($data['events_selected'], true);
        if (is_array($decoded)) {
            $events_selected = $decoded;
        } else {
            $events_selected = array_filter(array_map('trim', explode(',', $data['events_selected'])));
        }
    }
}
$eventsJson = json_encode($events_selected);

$project_title = trim($data['project_title'] ?? $data['team_name'] ?? 'InnoWave Participant');
$track         = trim($data['track'] ?? 'Open Innovation');
$description   = trim($data['description'] ?? 'Participation in INNOWAVE-2K26 Engineer\'s Day Celebration events.');
$leader_name   = trim($data['leader_name'] ?? '');
$leader_email  = trim($data['leader_email'] ?? '');
$leader_phone  = trim($data['leader_phone'] ?? '');
$college_name  = trim($data['college_name'] ?? '');
$roll_no       = trim($data['roll_no'] ?? '');
$branch        = trim($data['branch'] ?? '');
$year          = trim($data['year'] ?? '');

$ieee_member_input = trim($data['ieee_member'] ?? '');
$ieee_id           = trim($data['ieee_id'] ?? '');
$ieee_card         = trim($data['ieee_card'] ?? '');
$ieee_email        = trim($data['ieee_email'] ?? '');
$ieee_grade        = trim($data['ieee_grade'] ?? '');

$team_size_param   = intval($data['team_size'] ?? 1);
$ieee_count        = intval($data['ieee_count'] ?? ($ieee_member_input === 'Yes' ? $team_size_param : 0));
$non_ieee_count    = intval($data['non_ieee_count'] ?? ($ieee_member_input === 'No' ? $team_size_param : 0));

if ($ieee_count <= 0 && $non_ieee_count <= 0) {
    if ($ieee_member_input === 'Yes') {
        $ieee_count = max(1, $team_size_param);
    } else {
        $non_ieee_count = max(1, $team_size_param);
    }
}

$team_size = max(1, $ieee_count + $non_ieee_count);
$ieee_member = $ieee_count > 0 ? 'Yes' : 'No';

$member2 = trim($data['member2'] ?? '');
$member3 = trim($data['member3'] ?? '');
$member4 = trim($data['member4'] ?? '');

// Validation
if (empty($leader_name)) {
    $errors[] = 'Participant name is required.';
}
if (empty($leader_email) || !filter_var($leader_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
$cleanPhone = preg_replace('/\D/', '', $leader_phone);
if (strlen($cleanPhone) < 10) {
    $errors[] = 'A valid 10-digit phone number is required.';
}
if ($ieee_member === 'Yes' && empty($ieee_card)) {
    $errors[] = 'Please upload your IEEE Membership Card / Proof.';
}

if (!empty($errors)) {
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// Check for Existing Registrations (Duplicate Email & IEEE ID)
$cleanEmail = strtolower($leader_email);
$cleanIeee = trim($ieee_id);

if (!$existingId && !empty($cleanEmail)) {
    $checkStmt = $pdo->prepare("SELECT id, team_id FROM registrations WHERE LOWER(leader_email) = ? LIMIT 1");
    $checkStmt->execute([$cleanEmail]);
    $matchEmail = $checkStmt->fetch();
    if ($matchEmail) {
        $existingId = intval($matchEmail['id']);
    }
}

if (!$existingId && !empty($cleanIeee) && strlen($cleanIeee) >= 4) {
    $checkIeeeStmt = $pdo->prepare("SELECT id, team_id FROM registrations WHERE ieee_id = ? AND ieee_id != '' LIMIT 1");
    $checkIeeeStmt->execute([$cleanIeee]);
    $matchIeee = $checkIeeeStmt->fetch();
    if ($matchIeee) {
        $existingId = intval($matchIeee['id']);
    }
}

// Compute Fee
$isPscmr = false;
$cLower = strtolower($college_name);
if (strpos($cLower, 'pscmr') !== false || strpos($cLower, 'potti sriramulu') !== false || strpos($cLower, 'chalavadi') !== false || strpos($cLower, 'mallikarjuna rao') !== false) {
    $isPscmr = true;
}

$ieeeRate = $isPscmr ? 50 : 100;
$nonIeeeRate = $isPscmr ? 100 : 200;

$amount = ($ieee_count * $ieeeRate) + ($non_ieee_count * $nonIeeeRate);
$fee_label = '';
if ($ieee_count > 0 && $non_ieee_count > 0) {
    $fee_label = "{$ieee_count} IEEE (₹" . ($ieee_count * $ieeeRate) . ") + {$non_ieee_count} Non-IEEE (₹" . ($non_ieee_count * $nonIeeeRate) . ") = ₹{$amount}";
} else if ($ieee_count > 0) {
    $fee_label = "{$ieee_count} IEEE Member(s) × ₹{$ieeeRate} = ₹{$amount}";
} else {
    $fee_label = "{$non_ieee_count} Non-IEEE Member(s) × ₹{$nonIeeeRate} = ₹{$amount}";
}

$isIeeeMember = ($ieee_member === 'Yes');
$ieeeStatus = $isIeeeMember ? 'Card Approved' : 'N/A';
$initialPaymentStatus = 'Pending Payment Confirmation';
$ieeeOcrMismatch = 0;
$ieeeWarning = null;

// IEEE Card AI Verification Check (Client-Passed or OCR Flag)
if ($isIeeeMember && isset($data['ieee_ocr_passed']) && $data['ieee_ocr_passed'] === false) {
    echo json_encode([
        'ok' => false,
        'errors' => [
            "🚫 IEEE CARD AI VERIFICATION FAILED:\n\n" .
            (!empty($data['ieee_ocr_error']) ? $data['ieee_ocr_error'] : "Entered 9-digit IEEE ID or Name was NOT found inside your uploaded card proof image.") .
            "\n\nPlease re-upload a clear, legible screenshot of your official IEEE Membership Card showing your IEEE ID & Name."
        ]
    ]);
    exit;
}

$regId = $existingId;
$team_id = '';

if ($existingId) {
    $getStmt = $pdo->prepare("SELECT team_id FROM registrations WHERE id = ?");
    $getStmt->execute([$existingId]);
    $exRow = $getStmt->fetch();
    if ($exRow) {
        $team_id = $exRow['team_id'];
        $updStmt = $pdo->prepare("
            UPDATE registrations SET
                project_title = ?, track = ?, events_selected = ?, description = ?,
                leader_name = ?, leader_email = ?, leader_phone = ?, college_name = ?,
                roll_no = ?, branch = ?, year = ?, ieee_member = ?, ieee_id = ?, ieee_card = ?,
                ieee_verification_status = ?, ieee_email = ?, ieee_grade = ?, ieee_count = ?, non_ieee_count = ?,
                team_size = ?, member2 = ?, member3 = ?, member4 = ?, amount = ?, fee_label = ?
            WHERE id = ?
        ");
        $updStmt->execute([
            $project_title, $track, $eventsJson, $description,
            $leader_name, $leader_email, $leader_phone, $college_name,
            $roll_no, $branch, $year, $ieee_member, $ieee_id, $ieee_card,
            $ieeeStatus, $ieee_email, $ieee_grade, $ieee_count, $non_ieee_count,
            $team_size, $member2, $member3, $member4, $amount, $fee_label,
            $existingId
        ]);
    }
}

if (empty($team_id)) {
    // Generate new sequential Team ID (IW26-XXXX)
    $maxStmt = $pdo->query("SELECT MAX(reg_seq) as max_seq FROM registrations");
    $maxRow = $maxStmt ? $maxStmt->fetch() : null;
    $nextSeq = ($maxRow && !empty($maxRow['max_seq'])) ? intval($maxRow['max_seq']) + 1 : 1;
    $team_id = 'IW26-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    $createdAt = date('Y-m-d H:i:s');

    $insertStmt = $pdo->prepare("
        INSERT INTO registrations (
            team_id, reg_seq, project_title, track, events_selected, description,
            leader_name, leader_email, leader_phone, college_name, roll_no, branch, year,
            ieee_member, ieee_id, ieee_card, ieee_verification_status, ieee_email, ieee_grade, ieee_count, non_ieee_count,
            team_size, member2, member3, member4, amount, fee_label, payment_mode, payment_status,
            ieee_ocr_mismatch, ieee_warning, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, 'UPI', ?,
            ?, ?, ?
        )
    ");

    try {
        $insertStmt->execute([
            $team_id, $nextSeq, $project_title, $track, $eventsJson, $description,
            $leader_name, $leader_email, $leader_phone, $college_name, $roll_no, $branch, $year,
            $ieee_member, $ieee_id, $ieee_card, $ieeeStatus, $ieee_email, $ieee_grade, $ieee_count, $non_ieee_count,
            $team_size, $member2, $member3, $member4, $amount, $fee_label, $initialPaymentStatus,
            $ieeeOcrMismatch, $ieeeWarning, $createdAt
        ]);
        $regId = intval($pdo->lastInsertId());
    } catch (Exception $ex) {
        $msg = $ex->getMessage();
        if (strpos($msg, 'UNIQUE') !== false || strpos($msg, 'Duplicate entry') !== false) {
            if (strpos($msg, 'leader_email') !== false) {
                echo json_encode(['ok' => false, 'errors' => ['🚫 Email address is already registered.']]);
            } else if (strpos($msg, 'ieee_id') !== false) {
                echo json_encode(['ok' => false, 'errors' => ['🚫 IEEE Membership ID is already registered.']]);
            } else {
                echo json_encode(['ok' => false, 'errors' => ['🚫 A registration record with matching details already exists.']]);
            }
        } else {
            echo json_encode(['ok' => false, 'errors' => ['⚠️ Registration database error: ' . $msg]]);
        }
        exit;
    }
}

// Build UPI intent & note
$vpa = '6309419599@axl';
$payeeName = 'PSCMR IEEE Student Branch';
$note = "InnoWave-2k26 {$team_id}";
$upiUri = "upi://pay?pa=" . urlencode($vpa) . "&pn=" . urlencode($payeeName) . "&am={$amount}&cu=INR&tn=" . urlencode($note);

echo json_encode([
    'ok' => true,
    'id' => intval($regId),
    'team_id' => $team_id,
    'amount' => $amount,
    'fee_label' => $fee_label,
    'is_ieee' => $isIeeeMember,
    'ieee_verification_status' => $ieeeStatus,
    'upi' => [
        'vpa' => $vpa,
        'name' => $payeeName,
        'note' => $note,
        'upiUri' => $upiUri
    ]
]);
