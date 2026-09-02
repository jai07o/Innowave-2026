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

if ($action === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=innowave_2k26_registrations_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID', 'Team ID', 'Participant Name', 'Email', 'Phone', 'College', 'Roll No',
        'Branch', 'Year', 'IEEE Member', 'IEEE ID', 'IEEE Verification Status',
        'Events Selected', 'Amount (₹)', 'Fee Label', 'Payment Status', 'Payment Ref (UTR)', 'Registered At'
    ]);

    $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id ASC");
    while ($r = $stmt->fetch()) {
        $eventsStr = $r['events_selected'] ?? '';
        $decoded = json_decode($eventsStr, true);
        if (is_array($decoded)) {
            $eventsStr = implode(', ', $decoded);
        }

        fputcsv($output, [
            $r['id'],
            $r['team_id'],
            $r['leader_name'],
            $r['leader_email'],
            $r['leader_phone'],
            $r['college_name'],
            $r['roll_no'],
            $r['branch'],
            $r['year'],
            $r['ieee_member'],
            $r['ieee_id'],
            $r['ieee_verification_status'] ?? 'N/A',
            $eventsStr,
            $r['amount'],
            $r['fee_label'],
            $r['payment_status'],
            $r['payment_ref'],
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown admin action specified.']);
