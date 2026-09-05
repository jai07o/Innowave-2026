<?php
/**
 * INNOWAVE-2K26 — Admin Portal API Endpoint (PHP + MySQL)
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true) ?: [];

$action = $_REQUEST['action'] ?? $jsonBody['action'] ?? '';
$pass   = $_REQUEST['password'] ?? $jsonBody['password'] ?? $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';

if (empty($pass) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (strpos($auth, 'Bearer ') === 0) {
        $pass = substr($auth, 7);
    }
}

$ADMIN_PASSWORD = 'innowave2k26';

function isAuthorizedPassword($p) {
    global $ADMIN_PASSWORD;
    if (empty($p)) return false;
    $pClean = trim($p);
    return ($pClean === $ADMIN_PASSWORD || $pClean === 'innowave2026' || $pClean === 'innowave2k26');
}

if ($action === 'login') {
    $loginPw = $jsonBody['password'] ?? $pass;
    if (isAuthorizedPassword($loginPw)) {
        echo json_encode(['ok' => true, 'token' => $ADMIN_PASSWORD, 'message' => 'Admin authenticated successfully.']);
    } else {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Incorrect admin password.']);
    }
    exit;
}

if (!isAuthorizedPassword($pass)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized admin access.']);
    exit;
}

if ($action === 'list' || empty($action)) {
    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id DESC");
    $rows = $stmt->fetchAll();

    $totalTeams = count($rows);
    $ieeeParticipants = 0;
    $nonIeeeParticipants = 0;
    $totalExpectedAmount = 0;
    $amountCollected = 0;
    $amountPendingVerification = 0;
    $pendingVerification = 0;
    $ieeeTeams = 0;
    $nonIeeeTeams = 0;

    foreach ($rows as $r) {
        $ic = intval($r['ieee_count'] ?? 0);
        $nic = intval($r['non_ieee_count'] ?? 0);

        if ($ic > 0 || $nic > 0) {
            $ieeeParticipants += $ic;
            $nonIeeeParticipants += $nic;
        } else {
            if (($r['ieee_member'] ?? '') === 'Yes') {
                $ieeeParticipants += 1;
            } else {
                $nonIeeeParticipants += 1;
            }
        }

        if (($r['ieee_member'] ?? '') === 'Yes') {
            $ieeeTeams++;
        } else {
            $nonIeeeTeams++;
        }

        $amt = intval($r['amount'] ?? 0);
        $totalExpectedAmount += $amt;
        $st = $r['payment_status'] ?? '';

        if ($st === 'Paid' || $st === 'Confirmed') {
            $amountCollected += $amt;
        } else if ($st === 'Pending Verification' || $st === 'Pending Payment Confirmation') {
            $amountPendingVerification += $amt;
            $pendingVerification++;
        }
    }

    $totalParticipants = $ieeeParticipants + $nonIeeeParticipants;
    $ieeeMoney = $ieeeParticipants * 100;
    $nonIeeeMoney = $nonIeeeParticipants * 200;
    $collectionGap = $totalExpectedAmount - $amountCollected;

    echo json_encode([
        'ok' => true,
        'totalTeams' => $totalTeams,
        'totalParticipants' => $totalParticipants,
        'ieeeParticipants' => $ieeeParticipants,
        'ieeeMoney' => $ieeeMoney,
        'nonIeeeParticipants' => $nonIeeeParticipants,
        'nonIeeeMoney' => $nonIeeeMoney,
        'totalExpectedAmount' => $totalExpectedAmount,
        'amountCollected' => $amountCollected,
        'amountPendingVerification' => $amountPendingVerification,
        'collectionGap' => $collectionGap,
        'pendingVerification' => $pendingVerification,
        'ieeeTeams' => $ieeeTeams,
        'nonIeeeTeams' => $nonIeeeTeams,
        'stats' => [
            'total' => $totalTeams,
            'ieee' => $ieeeParticipants,
            'non_ieee' => $nonIeeeParticipants,
            'amount' => $amountCollected,
            'pending_ieee' => $pendingVerification
        ],
        'rows' => $rows,
        'data' => $rows
    ]);
    exit;
}

if ($action === 'approve-ieee') {
    $id = intval($jsonBody['id'] ?? $_POST['id'] ?? 0);
    $status = trim($jsonBody['status'] ?? $_POST['status'] ?? 'Card Approved');

    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'Registration ID is required.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE registrations SET ieee_verification_status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    echo json_encode(['ok' => true, 'id' => $id, 'status' => $status]);
    exit;
}

if ($action === 'confirm-payment') {
    $id = intval($jsonBody['id'] ?? $_POST['id'] ?? 0);
    $status = trim($jsonBody['status'] ?? $_POST['status'] ?? 'Paid');

    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'Registration ID is required.']);
        exit;
    }

    $paidAt = ($status === 'Paid' || $status === 'Confirmed') ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare("UPDATE registrations SET payment_status = ?, paid_at = ? WHERE id = ?");
    $stmt->execute([$status, $paidAt, $id]);

    echo json_encode(['ok' => true, 'id' => $id, 'payment_status' => $status]);
    exit;
}

if ($action === 'delete') {
    $id = intval($jsonBody['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'ID required for deletion.']);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM registrations WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['ok' => true, 'id' => $id, 'message' => 'Registration record deleted.']);
    exit;
}

if ($action === 'delete-all') {
    $pdo->exec("TRUNCATE TABLE registrations");
    echo json_encode(['ok' => true, 'deleted' => true, 'message' => 'All registration records deleted successfully.']);
    exit;
}

if ($action === 'export' || $action === 'export-php') {
    // 1. Excel Export tailored for PHP Admin (admin.php)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=innowave_2k26_php_admin_export_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    // Output UTF-8 BOM for Microsoft Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'S.No',
        'Participant ID',
        'Payment Status',
        'Amount (₹)',
        'Fee Details / Matrix Label',
        'Participant Name',
        'Email Address',
        'Phone Number',
        'College / Institution',
        'PSCMR Admission / Roll Number',
        'Branch / Department',
        'Year of Study',
        'IEEE Member (Yes/No)',
        'IEEE Membership ID',
        'IEEE Card Verification Status',
        'Selected Events',
        '12-Digit UTR / Transaction Ref',
        'Payment Verification Flag',
        'Payment Screenshot Uploaded',
        'Registration Date & Time'
    ]);

    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id ASC");
    $sno = 1;
    while ($r = $stmt->fetch()) {
        $eventsStr = $r['events_selected'] ?? '';
        $decoded = json_decode($eventsStr, true);
        if (is_array($decoded)) {
            $eventsStr = implode(', ', $decoded);
        }

        $flag = 'Clean';
        if (!empty($r['duplicate_utr'])) {
            $flag = 'Duplicate UTR (' . ($r['utr_warning'] ?: 'Reused UTR') . ')';
        } elseif (!empty($r['utr_mismatch'])) {
            $flag = 'UTR Mismatch (' . ($r['utr_warning'] ?: 'Not detected in screenshot') . ')';
        }

        fputcsv($output, [
            $sno++,
            $r['team_id'] ?: 'IW26-' . str_pad($r['id'], 4, '0', STR_PAD_LEFT),
            $r['payment_status'] ?: 'Pending Confirmation',
            $r['amount'] ?: '0',
            $r['fee_label'] ?: 'N/A',
            $r['leader_name'] ?: 'N/A',
            $r['leader_email'] ?: 'N/A',
            $r['leader_phone'] ?: 'N/A',
            $r['college_name'] ?: 'N/A',
            $r['roll_no'] ?: 'N/A',
            $r['branch'] ?: 'N/A',
            $r['year'] ?: 'N/A',
            $r['ieee_member'] ?: 'No',
            $r['ieee_id'] ?: 'N/A',
            $r['ieee_verification_status'] ?: 'N/A',
            $eventsStr ?: 'All 6 Brochure Events',
            $r['payment_ref'] ?: 'N/A',
            $flag,
            !empty($r['payment_screenshot']) || !empty($r['payment_proof']) ? 'Yes (Uploaded)' : 'No',
            $r['created_at'] ?: date('Y-m-d H:i:s')
        ]);
    }
    fclose($output);
    exit;
}

if ($action === 'export-html') {
    // 2. Excel Export tailored for HTML Admin (admin.html)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=innowave_2k26_html_admin_export_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    // Output UTF-8 BOM for Microsoft Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'S.No',
        'Participant ID',
        'Payment Status',
        'Amount (₹)',
        '12-Digit UTR Reference',
        'UTR Warning / Mismatch Flag',
        'Participant Name',
        'Email Address',
        'Phone Number',
        'College Name',
        'Admission / Roll Number',
        'Branch',
        'Year of Study',
        'IEEE Status',
        'IEEE Membership ID',
        'IEEE Card Verification Status',
        'IEEE Card Uploaded',
        'Payment Screenshot Uploaded',
        'Selected Events',
        'Registered Timestamp'
    ]);

    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id ASC");
    $sno = 1;
    while ($r = $stmt->fetch()) {
        $eventsStr = $r['events_selected'] ?? '';
        $decoded = json_decode($eventsStr, true);
        if (is_array($decoded)) {
            $eventsStr = implode(', ', $decoded);
        }

        $warning = $r['utr_warning'] ?: 'None';
        if (!empty($r['duplicate_utr'])) {
            $warning = 'DUPLICATE: ' . $warning;
        } elseif (!empty($r['utr_mismatch'])) {
            $warning = 'MISMATCH: ' . $warning;
        }

        fputcsv($output, [
            $sno++,
            $r['team_id'] ?: 'IW26-' . str_pad($r['id'], 4, '0', STR_PAD_LEFT),
            $r['payment_status'] ?: 'Pending',
            $r['amount'] ?: '0',
            $r['payment_ref'] ?: 'N/A',
            $warning,
            $r['leader_name'] ?: 'N/A',
            $r['leader_email'] ?: 'N/A',
            $r['leader_phone'] ?: 'N/A',
            $r['college_name'] ?: 'N/A',
            $r['roll_no'] ?: 'N/A',
            $r['branch'] ?: 'N/A',
            $r['year'] ?: 'N/A',
            $r['ieee_member'] === 'Yes' ? 'IEEE Member' : 'Non-IEEE',
            $r['ieee_id'] ?: 'N/A',
            $r['ieee_verification_status'] ?: 'N/A',
            !empty($r['ieee_card']) ? 'Yes' : 'No',
            !empty($r['payment_screenshot']) || !empty($r['payment_proof']) ? 'Yes' : 'No',
            $eventsStr ?: 'All Events',
            $r['created_at'] ?: date('Y-m-d H:i:s')
        ]);
    }
    fclose($output);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown admin action specified.']);
