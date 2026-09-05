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

// Compute Fee (Other College IEEE Member = EXACTLY ₹100 | PSCMR CET IEEE = ₹50)
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

// 🚫 STRICT ANTI-CLONING & DUPLICATE BLOCK LOGIC

// 1. Strictly Block Duplicate Email Address
$cleanEmail = strtolower(trim($leader_email));
if (!empty($cleanEmail)) {
    $emailSql = "SELECT id, team_id, leader_name FROM registrations WHERE LOWER(TRIM(leader_email)) = ?";
    $emailParams = [$cleanEmail];
    if (!empty($existingId)) {
        $emailSql .= " AND id != ?";
        $emailParams[] = $existingId;
    }
    $emailSql .= " LIMIT 1";
    $emailStmt = $pdo->prepare($emailSql);
    $emailStmt->execute($emailParams);
    $dupEmail = $emailStmt->fetch();
    if ($dupEmail) {
        echo json_encode([
            'ok' => false,
            'errors' => [
                "🚫 DUPLICATE EMAIL ADDRESS DETECTED:\n\nThe email address '{$cleanEmail}' is ALREADY registered with Participant ID {$dupEmail['team_id']}.\n\nCloned or duplicate submissions using the same email address are strictly blocked. Please use your unique email address or check your status on the homepage."
            ]
        ]);
        exit;
    }
}

// 2. Strictly Block Duplicate IEEE Membership ID
$cleanIeee = trim($ieee_id);
if ($ieee_member === 'Yes' && !empty($cleanIeee) && strlen($cleanIeee) >= 4) {
    $ieeeSql = "SELECT id, team_id, leader_name FROM registrations WHERE TRIM(ieee_id) = ? AND TRIM(ieee_id) != ''";
    $ieeeParams = [$cleanIeee];
    if (!empty($existingId)) {
        $ieeeSql .= " AND id != ?";
        $ieeeParams[] = $existingId;
    }
    $ieeeSql .= " LIMIT 1";
    $ieeeStmt = $pdo->prepare($ieeeSql);
    $ieeeStmt->execute($ieeeParams);
    $dupIeee = $ieeeStmt->fetch();
    if ($dupIeee) {
        echo json_encode([
            'ok' => false,
            'errors' => [
                "🚫 DUPLICATE IEEE MEMBERSHIP ID DETECTED:\n\nThe IEEE Membership Number '{$cleanIeee}' has ALREADY been registered by participant {$dupIeee['leader_name']} ({$dupIeee['team_id']}).\n\nCloned or shared IEEE Membership IDs are strictly prohibited. Each IEEE Membership ID can only be registered once."
            ]
        ]);
        exit;
    }
}

// 3. Strictly Validate and Block Duplicate Admission / Roll Number (for PSCMR CET participants)
$cleanRoll = strtoupper(trim($roll_no));
if ($isPscmr) {
    if (empty($cleanRoll)) {
        echo json_encode([
            'ok' => false,
            'errors' => [
                "⚠️ GIVE FULL ADMISSION NUMBER:\n\nPSCMR CET students must provide their full College Admission / Roll Number (10 to 11 characters)."
            ]
        ]);
        exit;
    }
    $rollLen = strlen($cleanRoll);
    if ($rollLen < 10 || $rollLen > 11) {
        echo json_encode([
            'ok' => false,
            'errors' => [
                "⚠️ GIVE FULL ADMISSION NUMBER:\n\nPlease enter your full admission number (must be 10 to 11 characters, e.g. 22HP1A0501). You entered {$rollLen} character(s)."
            ]
        ]);
        exit;
    }

    $rollSql = "SELECT id, team_id, leader_name FROM registrations WHERE UPPER(TRIM(roll_no)) = ? AND TRIM(roll_no) != ''";
    $rollParams = [$cleanRoll];
    if (!empty($existingId)) {
        $rollSql .= " AND id != ?";
        $rollParams[] = $existingId;
    }
    $rollSql .= " LIMIT 1";
    $rollStmt = $pdo->prepare($rollSql);
    $rollStmt->execute($rollParams);
    $dupRoll = $rollStmt->fetch();
    if ($dupRoll) {
        echo json_encode([
            'ok' => false,
            'errors' => [
                "🚫 DUPLICATE ADMISSION / ROLL NUMBER DETECTED:\n\nThe PSCMR CET Admission / Roll Number '{$cleanRoll}' has ALREADY been registered with Participant ID {$dupRoll['team_id']}.\n\nMultiple registrations under the same student roll number are strictly blocked."
            ]
        ]);
        exit;
    }
}

// 4. Strictly Block Duplicate UTR Reference Number (if provided in registration payload)
$submittedUtr = trim($data['payment_ref'] ?? $data['utrRef'] ?? '');
if (!empty($submittedUtr)) {
    $cleanUtr = strtoupper(preg_replace('/\s+/', '', $submittedUtr));
    if (strlen($cleanUtr) >= 6) {
        $utrSql = "SELECT id, team_id, leader_name FROM registrations WHERE payment_ref IS NOT NULL AND TRIM(payment_ref) != '' AND UPPER(REPLACE(TRIM(payment_ref), ' ', '')) = ?";
        $utrParams = [$cleanUtr];
        if (!empty($existingId)) {
            $utrSql .= " AND id != ?";
            $utrParams[] = $existingId;
        }
        $utrSql .= " LIMIT 1";
        $utrStmt = $pdo->prepare($utrSql);
        $utrStmt->execute($utrParams);
        $dupUtr = $utrStmt->fetch();
        if ($dupUtr) {
            echo json_encode([
                'ok' => false,
                'errors' => [
                    "🚫 DUPLICATE UTR DETECTED:\n\nThe UTR Reference ID '{$cleanUtr}' has ALREADY been submitted by another participant ({$dupUtr['team_id']}).\n\nCloned or reused payment transaction references are strictly blocked."
                ]
            ]);
            exit;
        }
    }
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

// Build UPI intent & note for POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE
$vpa = '1414155000131347@kvbl0001414.ifsc';
$payeeName = 'POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE';
$note = "InnoWave-2k26 {$team_id}";
$upiUri = "upi://pay?pa=" . urlencode($vpa) . "&pn=" . urlencode($payeeName) . "&am={$amount}&cu=INR&tn=" . urlencode($note);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=" . urlencode($upiUri);

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
        'upiUri' => $upiUri,
        'qr' => $qrUrl,
        'acc_no' => '1414155000131347',
        'ifsc' => 'KVBL0001414',
        'bank' => 'Karur Vysya Bank (KVB)'
    ]
]);
