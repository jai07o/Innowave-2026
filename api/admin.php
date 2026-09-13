<?php
/**
 * INNOWAVE-2K26 — Admin Portal API Endpoint (PHP + MySQL)
 */
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '120');

if (!ob_get_level()) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        @ob_start('ob_gzhandler');
    } else {
        @ob_start();
    }
}
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

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
    if (!empty($_SESSION['admin_auth'])) return true;
    if (empty($p)) return false;
    $pClean = trim($p);
    return ($pClean === $ADMIN_PASSWORD || $pClean === 'innowave2026' || $pClean === 'innowave2k26');
}

if ($action === 'check-auth') {
    if (isAuthorizedPassword($pass)) {
        echo json_encode(['ok' => true, 'token' => $ADMIN_PASSWORD]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

if ($action === 'login') {
    $loginPw = $jsonBody['password'] ?? $pass;
    if (isAuthorizedPassword($loginPw)) {
        $_SESSION['admin_auth'] = true;
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

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'MySQL Database is offline: ' . ($lastMysqlError ?: 'Please verify MySQL database credentials in api/config.php.')
    ]);
    exit;
}

if ($action === 'get-image') {
    $imgId = intval($_GET['id'] ?? 0);
    $type = trim($_GET['type'] ?? 'payment');
    if (!$imgId) {
        http_response_code(400);
        exit('Invalid record ID');
    }

    $stmt = $pdo->prepare("SELECT id, payment_screenshot, payment_proof, ieee_card FROM registrations WHERE id = ?");
    $stmt->execute([$imgId]);
    $imgRow = $stmt->fetch();
    if (!$imgRow) {
        http_response_code(404);
        exit('Record not found');
    }

    $rawImg = ($type === 'ieee') 
        ? ($imgRow['ieee_card'] ?? '') 
        : (!empty($imgRow['payment_screenshot']) ? $imgRow['payment_screenshot'] : ($imgRow['payment_proof'] ?? ''));

    if (empty($rawImg)) {
        http_response_code(404);
        exit('No image uploaded');
    }

    if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $rawImg, $m)) {
        header('Content-Type: image/' . $m[1]);
        header('Cache-Control: public, max-age=86400');
        echo base64_decode($m[2]);
        exit;
    } else {
        header('Location: ' . $rawImg);
        exit;
    }
}

if ($action === 'list' || empty($action)) {
    $viewMode = $_REQUEST['view'] ?? $jsonBody['view'] ?? 'all';
    
    $cols = "
        id, team_id, reg_seq, project_title, track, events_selected, description,
        leader_name, leader_email, leader_phone, college_name, roll_no, branch, year,
        ieee_member, ieee_id, ieee_verification_status, ieee_email, ieee_grade,
        ieee_count, non_ieee_count, team_size, member2, member3, member4,
        amount, fee_label, payment_mode, payment_status, payment_ref,
        duplicate_utr, utr_mismatch, utr_warning, ieee_ocr_mismatch, ieee_warning,
        paid_at, created_at,
        (CASE WHEN (payment_screenshot IS NOT NULL AND TRIM(payment_screenshot) != '' AND TRIM(payment_screenshot) != 'NULL')
                OR (payment_proof IS NOT NULL AND TRIM(payment_proof) != '' AND TRIM(payment_proof) != 'NULL')
              THEN 1 ELSE 0 END) AS has_payment_screenshot,
        (CASE WHEN (ieee_card IS NOT NULL AND TRIM(ieee_card) != '' AND TRIM(ieee_card) != 'NULL')
              THEN 1 ELSE 0 END) AS has_ieee_card
    ";

    try {
        $stmt = $pdo->query("SELECT {$cols} FROM registrations ORDER BY id DESC");
        $allRows = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        $allRows = [];
    }

    $completedRows = [];
    $incompleteRows = [];

    foreach ($allRows as &$r) {
        $hasScreenshot = !empty($r['has_payment_screenshot']);
        $hasIeee = !empty($r['has_ieee_card']);

        $r['has_payment_screenshot'] = $hasScreenshot ? 1 : 0;
        $r['has_ieee_card'] = $hasIeee ? 1 : 0;

        // Lightweight on-demand image streaming URLs (zero RAM footprint)
        if ($hasScreenshot) {
            $r['payment_screenshot'] = 'api/admin.php?action=get-image&id=' . $r['id'] . '&type=payment&password=' . urlencode($pass);
            $r['payment_proof'] = $r['payment_screenshot'];
        } else {
            $r['payment_screenshot'] = '';
            $r['payment_proof'] = '';
        }

        if ($hasIeee) {
            $r['ieee_card'] = 'api/admin.php?action=get-image&id=' . $r['id'] . '&type=ieee&password=' . urlencode($pass);
        } else {
            $r['ieee_card'] = '';
        }

        if ($hasScreenshot) {
            $completedRows[] = $r;
        } else {
            $incompleteRows[] = $r;
        }
    }
    unset($r);

    $allCount = count($allRows);
    $completedCount = count($completedRows);
    $incompleteCount = count($incompleteRows);

    if ($viewMode === 'completed') {
        $rows = $completedRows;
    } else if ($viewMode === 'incomplete') {
        $rows = $incompleteRows;
    } else {
        $rows = $allRows;
    }

    $totalTeams = count($allRows);
    $ieeeParticipants = 0;
    $nonIeeeParticipants = 0;
    $totalExpectedAmount = 0;
    $amountCollected = 0;
    $amountPendingVerification = 0;
    $pendingVerification = 0;
    $ieeeTeams = 0;
    $nonIeeeTeams = 0;

    $ieeeMoney = 0;
    $nonIeeeMoney = 0;

    foreach ($rows as $r) {
        $cClean = strtolower(preg_replace('/[^a-z0-9]/', '', $r['college_name'] ?? ''));
        $isPscmr = (
            strpos($cClean, 'pscmr') !== false ||
            strpos($cClean, 'pottisriramulu') !== false ||
            strpos($cClean, 'pottisreeramulu') !== false ||
            strpos($cClean, 'chalavadi') !== false ||
            strpos($cClean, 'mallikarjuna') !== false
        );
        $ieeeRate = $isPscmr ? 50 : 100;
        $nonIeeeRate = $isPscmr ? 100 : 200;

        $ic = intval($r['ieee_count'] ?? 0);
        $nic = intval($r['non_ieee_count'] ?? 0);

        if ($ic <= 0 && $nic <= 0) {
            if (($r['ieee_member'] ?? '') === 'Yes') {
                $ic = max(1, intval($r['team_size'] ?? 1));
            } else {
                $nic = max(1, intval($r['team_size'] ?? 1));
            }
        }

        $ieeeParticipants += $ic;
        $nonIeeeParticipants += $nic;

        $rIeeeAmt = $ic * $ieeeRate;
        $rNonIeeeAmt = $nic * $nonIeeeRate;
        $ieeeMoney += $rIeeeAmt;
        $nonIeeeMoney += $rNonIeeeAmt;

        if (($r['ieee_member'] ?? '') === 'Yes') {
            $ieeeTeams++;
        } else {
            $nonIeeeTeams++;
        }

        $amt = intval($r['amount'] ?? 0);
        if ($amt <= 0) {
            $amt = $rIeeeAmt + $rNonIeeeAmt;
        }
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
    $collectionGap = max(0, $totalExpectedAmount - $amountCollected);

    echo json_encode([
        'ok' => true,
        'view' => $viewMode,
        'allCount' => $allCount,
        'completedCount' => $completedCount,
        'incompleteCount' => $incompleteCount,
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
        'db_engine' => $activeEngine ?? 'mysql',
        'db_connected' => ($pdo !== null),
        'db_name' => $cfg_name ?? 'innowave_db',
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

    $stmt = $pdo->query("
        SELECT * FROM registrations 
        WHERE (payment_screenshot IS NOT NULL AND TRIM(payment_screenshot) != '' AND TRIM(payment_screenshot) != 'NULL')
           OR (payment_proof IS NOT NULL AND TRIM(payment_proof) != '' AND TRIM(payment_proof) != 'NULL')
        ORDER BY id ASC
    ");
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

    $stmt = $pdo->query("
        SELECT * FROM registrations 
        WHERE (payment_screenshot IS NOT NULL AND TRIM(payment_screenshot) != '' AND TRIM(payment_screenshot) != 'NULL')
           OR (payment_proof IS NOT NULL AND TRIM(payment_proof) != '' AND TRIM(payment_proof) != 'NULL')
        ORDER BY id ASC
    ");
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
