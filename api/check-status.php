<?php
/**
 * INNOWAVE-2K26 — Registration & Payment Status Check API Endpoint (PHP + MySQL)
 */
if (!ob_get_level()) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        @ob_start('ob_gzhandler');
    } else {
        @ob_start();
    }
}
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? $_GET['id'] ?? '');

if (empty($q)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter your Registration ID, Mobile, or Email.']);
    exit;
}

if (!isset($pdo) || !$pdo) {
    echo json_encode(['ok' => false, 'error' => 'Database connection unavailable. Please contact the administrator.']);
    exit;
}

try {
    $cleanQ = strtolower($q);
    $compactQ = str_replace(' ', '', $cleanQ);
    $strippedQ = str_replace(['-', ' ', '_'], '', $cleanQ);
    $digitsOnly = preg_replace('/\D/', '', $q);

    $whereClauses = [];
    $params = [];

    // 1. Match Team ID variants
    $whereClauses[] = "LOWER(TRIM(team_id)) = ?";
    $params[] = $cleanQ;

    $whereClauses[] = "REPLACE(LOWER(team_id), ' ', '') = ?";
    $params[] = $compactQ;

    $whereClauses[] = "REPLACE(REPLACE(LOWER(team_id), ' ', ''), '-', '') = ?";
    $params[] = $strippedQ;

    // 2. Match Email address
    $whereClauses[] = "LOWER(TRIM(leader_email)) = ?";
    $params[] = $cleanQ;

    // 3. Match IEEE ID
    $whereClauses[] = "(ieee_id IS NOT NULL AND TRIM(ieee_id) != '' AND LOWER(TRIM(ieee_id)) = ?)";
    $params[] = $cleanQ;

    // 4. Match Payment UTR reference
    $whereClauses[] = "(payment_ref IS NOT NULL AND TRIM(payment_ref) != '' AND LOWER(TRIM(payment_ref)) = ?)";
    $params[] = $cleanQ;

    // 5. Match Roll / Admission Number
    $whereClauses[] = "(roll_no IS NOT NULL AND TRIM(roll_no) != '' AND LOWER(TRIM(roll_no)) = ?)";
    $params[] = $cleanQ;

    // 6. Numeric ID checks (e.g. participant entered "5", "10", or "IW26-0005")
    if (!empty($digitsOnly) && strlen($digitsOnly) <= 6) {
        $numVal = intval($digitsOnly);
        $whereClauses[] = "id = ?";
        $params[] = $numVal;

        $whereClauses[] = "reg_seq = ?";
        $params[] = $numVal;

        $whereClauses[] = "team_id LIKE ?";
        $params[] = '%-' . str_pad($numVal, 4, '0', STR_PAD_LEFT);
    }

    // 7. Phone number match (min 5 digits)
    if (!empty($digitsOnly) && strlen($digitsOnly) >= 5) {
        $whereClauses[] = "(REPLACE(REPLACE(leader_phone, '+', ''), ' ', '') LIKE ?)";
        $params[] = '%' . $digitsOnly . '%';
    }

    // 8. Participant name partial match (min 3 chars)
    if (strlen($cleanQ) >= 3) {
        $whereClauses[] = "(LOWER(leader_name) LIKE ?)";
        $params[] = '%' . $cleanQ . '%';
    }

    $sql = "SELECT * FROM registrations WHERE " . implode(" OR ", $whereClauses) . " ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode([
            'ok' => false,
            'found' => false,
            'error' => 'Registration record not found. Please check your Registration ID, Mobile, Email, IEEE ID, or UTR Reference.'
        ]);
        exit;
    }

    $isIeee = ($row['ieee_member'] === 'Yes');
    $ieeeStatus = $row['ieee_verification_status'] ?: ($isIeee ? 'Card Approved' : 'N/A');
    $paymentStatus = $row['payment_status'] ?: 'Pending Payment Confirmation';

    // Compute expected fee strictly based on participant details (50, 100, 200):
    $cClean = strtolower(preg_replace('/[^a-z0-9]/', '', $row['college_name'] ?? ''));
    $isPscmr = (
        strpos($cClean, 'pscmr') !== false ||
        strpos($cClean, 'pottisriramulu') !== false ||
        strpos($cClean, 'pottisreeramulu') !== false ||
        strpos($cClean, 'chalavadi') !== false ||
        strpos($cClean, 'mallikarjuna') !== false
    );
    $expectedAmt = $isPscmr ? ($isIeee ? 50 : 100) : ($isIeee ? 100 : 200);
    $expectedLabel = $isPscmr
        ? ($isIeee ? 'PSCMR CET IEEE Member Delegate Fee: ₹50' : 'PSCMR CET Non-IEEE Student Delegate Fee: ₹100')
        : ($isIeee ? 'Other College IEEE Member Delegate Fee: ₹100' : 'Other College Non-IEEE Student Delegate Fee: ₹200');

    $finalAmount = in_array(intval($row['amount'] ?? 0), [50, 100, 200]) ? intval($row['amount']) : $expectedAmt;
    $finalFeeLabel = !empty($row['fee_label']) ? $row['fee_label'] : $expectedLabel;

    $payeeName = 'POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE';
    $accNo = '1414155000131347';
    $ifsc = 'KVBL0001414';
    $bank = 'Karur Vysya Bank (KVB)';

    $responseData = [
        'id' => intval($row['id']),
        'team_id' => $row['team_id'],
        'leader_name' => $row['leader_name'],
        'leader_email' => $row['leader_email'],
        'leader_phone' => $row['leader_phone'],
        'college_name' => $row['college_name'],
        'branch' => $row['branch'],
        'year' => $row['year'],
        'ieee_member' => $row['ieee_member'],
        'ieee_id' => $row['ieee_id'],
        'ieee_verification_status' => $ieeeStatus,
        'payment_status' => $paymentStatus,
        'payment_ref' => $row['payment_ref'],
        'has_payment_screenshot' => (!empty($row['payment_screenshot']) || !empty($row['payment_proof'])),
        'has_payment_ref' => (!empty($row['payment_ref']) && trim($row['payment_ref']) !== ''),
        'amount' => $finalAmount,
        'fee_label' => $finalFeeLabel,
        'created_at' => $row['created_at'],
        'bank_details' => [
            'beneficiary' => $payeeName,
            'acc_no' => $accNo,
            'ifsc' => $ifsc,
            'bank' => $bank
        ]
    ];

    echo json_encode(array_merge(['ok' => true, 'found' => true, 'data' => $responseData], $responseData));
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Error querying registration record: ' . $e->getMessage()
    ]);
}
