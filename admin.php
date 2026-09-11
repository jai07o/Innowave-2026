<?php
/**
 * INNOWAVE-2K26 — Native PHP Admin Dashboard Engine
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
require_once __DIR__ . '/api/db.php';

$ADMIN_PASSWORD = 'innowave2k26';
session_start();

$authError = '';
if (isset($_POST['action']) && $_POST['action'] === 'login') {
  $pw = trim($_POST['password'] ?? '');
  if ($pw === $ADMIN_PASSWORD || $pw === 'innowave2026' || $pw === 'innowave2k26') {
    $_SESSION['admin_auth'] = true;
  } else {
    $authError = 'Incorrect password.';
  }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
  unset($_SESSION['admin_auth']);
  session_destroy();
  header('Location: admin.php');
  exit;
}

$isLoggedIn = !empty($_SESSION['admin_auth']);

// Direct Action Handler (Approve / Pending / Delete / Update Amount)
if ($isLoggedIn && isset($_GET['do_action']) && isset($_GET['id']) && isset($pdo)) {
  $id = intval($_GET['id']);
  $act = trim($_GET['do_action']);
  $nowStr = date('Y-m-d H:i:s');
  if ($act === 'approve_paid') {
    $stmt = $pdo->prepare("UPDATE registrations SET payment_status = 'Paid', paid_at = ? WHERE id = ?");
    $stmt->execute([$nowStr, $id]);
  } else if ($act === 'mark_pending') {
    $stmt = $pdo->prepare("UPDATE registrations SET payment_status = 'Pending Payment Confirmation' WHERE id = ?");
    $stmt->execute([$id]);
  } else if ($act === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM registrations WHERE id = ? OR team_id = ?");
    $stmt->execute([$id, strval($id)]);
  } else if ($act === 'update_amount' && isset($_GET['amount'])) {
    $newAmt = intval($_GET['amount']);
    if ($newAmt >= 0) {
      $stmt = $pdo->prepare("UPDATE registrations SET amount = ? WHERE id = ?");
      $stmt->execute([$newAmt, $id]);
    }
  }
  header('Location: admin.php');
  exit;
}

// Fetch Registrations Directly from Database
$rows = [];
if ($isLoggedIn && isset($pdo)) {
  try {
    // Auto-populate / standardize fees only if explicitly requested (prevents table write locks on regular views):
    if (!empty($_GET['recalc_amounts'])) {
      $pdo->exec("
                UPDATE registrations 
                SET amount = 50, fee_label = 'PSCMR CET IEEE Member Delegate Fee: ₹50'
                WHERE (
                    LOWER(college_name) LIKE '%pscmr%' OR 
                    LOWER(college_name) LIKE '%chalavadi%' OR 
                    LOWER(college_name) LIKE '%potti%' OR
                    LOWER(college_name) LIKE '%sriramulu%' OR
                    LOWER(college_name) LIKE '%sreeramulu%' OR
                    LOWER(college_name) LIKE '%mallikarjuna%'
                ) AND ieee_member = 'Yes' AND (amount IS NULL OR amount NOT IN (50, 100, 200) OR amount != 50);

                UPDATE registrations 
                SET amount = 100, fee_label = 'PSCMR CET Non-IEEE Student Delegate Fee: ₹100'
                WHERE (
                    LOWER(college_name) LIKE '%pscmr%' OR 
                    LOWER(college_name) LIKE '%chalavadi%' OR 
                    LOWER(college_name) LIKE '%potti%' OR
                    LOWER(college_name) LIKE '%sriramulu%' OR
                    LOWER(college_name) LIKE '%sreeramulu%' OR
                    LOWER(college_name) LIKE '%mallikarjuna%'
                ) AND (ieee_member != 'Yes' OR ieee_member IS NULL) AND (amount IS NULL OR amount NOT IN (50, 100, 200) OR amount != 100);

                UPDATE registrations 
                SET amount = 100, fee_label = 'Other College IEEE Member Delegate Fee: ₹100'
                WHERE NOT (
                    LOWER(college_name) LIKE '%pscmr%' OR 
                    LOWER(college_name) LIKE '%chalavadi%' OR 
                    LOWER(college_name) LIKE '%potti%' OR
                    LOWER(college_name) LIKE '%sriramulu%' OR
                    LOWER(college_name) LIKE '%sreeramulu%' OR
                    LOWER(college_name) LIKE '%mallikarjuna%'
                ) AND ieee_member = 'Yes' AND (amount IS NULL OR amount NOT IN (50, 100, 200) OR amount != 100);

                UPDATE registrations 
                SET amount = 200, fee_label = 'Other College Non-IEEE Student Delegate Fee: ₹200'
                WHERE NOT (
                    LOWER(college_name) LIKE '%pscmr%' OR 
                    LOWER(college_name) LIKE '%chalavadi%' OR 
                    LOWER(college_name) LIKE '%potti%' OR
                    LOWER(college_name) LIKE '%sriramulu%' OR
                    LOWER(college_name) LIKE '%sreeramulu%' OR
                    LOWER(college_name) LIKE '%mallikarjuna%'
                ) AND (ieee_member != 'Yes' OR ieee_member IS NULL) AND (amount IS NULL OR amount NOT IN (50, 100, 200) OR amount != 200);
            ");
    }

    // Count total registrations with screenshots vs incomplete (no screenshot)
    $completedStmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE (payment_screenshot IS NOT NULL AND TRIM(payment_screenshot) != '' AND TRIM(payment_screenshot) != 'NULL') OR (payment_proof IS NOT NULL AND TRIM(payment_proof) != '' AND TRIM(payment_proof) != 'NULL')");
    $completedCount = $completedStmt ? intval($completedStmt->fetchColumn()) : 0;

    $incStmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE (payment_screenshot IS NULL OR TRIM(payment_screenshot) = '' OR TRIM(payment_screenshot) = 'NULL') AND (payment_proof IS NULL OR TRIM(payment_proof) = '' OR TRIM(payment_proof) = 'NULL')");
    $incompleteCount = $incStmt ? intval($incStmt->fetchColumn()) : 0;

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
      $r['proof_url'] = $hasScreenshot ? ('api/admin.php?action=get-image&id=' . $r['id'] . '&type=payment&password=innowave2k26') : '';
      $r['ieee_card_url'] = $hasIeee ? ('api/admin.php?action=get-image&id=' . $r['id'] . '&type=ieee&password=innowave2k26') : '';

      // Set empty string placeholder (images stream on demand via proof_url)
      $r['payment_screenshot'] = '';
      $r['payment_proof'] = '';
      $r['ieee_card'] = '';

      if ($hasScreenshot) {
        $completedRows[] = $r;
      } else {
        $incompleteRows[] = $r;
      }
    }
    unset($r);

    $viewFilter = $_GET['view'] ?? 'all';
    if ($viewFilter === 'incomplete') {
      $rows = $incompleteRows;
    } else if ($viewFilter === 'completed') {
      $rows = $completedRows;
    } else {
      $rows = $allRows;
    }
  } catch (Exception $e) {
    $rows = [];
    $allRows = [];
    $completedRows = [];
    $incompleteRows = [];
  }
}

$totalRegs = count($rows);
$ieeeCount = 0;
$nonIeeeCount = 0;
$totalAmountCollected = 0;
$pendingCount = 0;

$utrCounts = [];
$rollCounts = [];
$emailCounts = [];
$ignoreRefs = ['N/A', 'NA', '-', '--', 'PENDING', 'PENDINGCONFIRMATION', 'PENDINGPAYMENT', 'PAID', 'NONE', 'NULL', 'NIL', 'NOTAVAILABLE', '0', '000000', '123456', 'NO', 'YES', 'UNVERIFIED', 'SUBMITTED', 'VERIFIED', 'WAITING', 'CONFIRMED'];

foreach ($rows as $r) {
  $rawRef = strtoupper(preg_replace('/[^A-Z0-9]/', '', $r['payment_ref'] ?? ''));
  if (!empty($rawRef) && strlen($rawRef) >= 6 && !in_array($rawRef, $ignoreRefs)) {
    $utrCounts[$rawRef] = ($utrCounts[$rawRef] ?? 0) + 1;
  }

  $em = strtolower(trim($r['leader_email'] ?? ''));
  if (!empty($em)) {
    $emailCounts[$em] = ($emailCounts[$em] ?? 0) + 1;
  }

  $roll = strtoupper(trim($r['roll_no'] ?? ''));
  if (!empty($roll) && strlen($roll) >= 6 && $roll !== 'N/A' && $roll !== '-') {
    $rollCounts[$roll] = ($rollCounts[$roll] ?? 0) + 1;
  }
}

foreach ($rows as &$r) {
  $cClean = strtolower(preg_replace('/[^a-z0-9]/', '', $r['college_name'] ?? ''));
  $isPscmr = (
    strpos($cClean, 'pscmr') !== false ||
    strpos($cClean, 'pottisriramulu') !== false ||
    strpos($cClean, 'pottisreeramulu') !== false ||
    strpos($cClean, 'chalavadi') !== false ||
    strpos($cClean, 'mallikarjuna') !== false
  );
  $isIeee = (($r['ieee_member'] ?? '') === 'Yes');

  if ($isIeee) {
    $ieeeCount++;
  } else {
    $nonIeeeCount++;
  }

  // Strictly enforce 50, 100, 200 based on participant details:
  // PSCMR IEEE = ₹50 | PSCMR Non-IEEE = ₹100
  // Other College IEEE = ₹100 | Other College Non-IEEE = ₹200
  $expectedAmt = $isPscmr ? ($isIeee ? 50 : 100) : ($isIeee ? 100 : 200);
  $currentAmt = (isset($r['amount']) && in_array(intval($r['amount']), [50, 100, 200])) ? intval($r['amount']) : $expectedAmt;
  $r['amount'] = $currentAmt;

  $st = strtolower(trim($r['payment_status'] ?? ''));
  if ($st === 'paid' || $st === 'confirmed' || $st === 'approved' || strpos($st, 'paid') !== false) {
    $totalAmountCollected += $currentAmt;
  } else {
    $pendingCount++;
  }
}
unset($r);

function esc($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InnoWave2k26 · Organizer Admin Dashboard</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap');

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --bg-primary: #030712;
      --bg-panel: rgba(15, 23, 42, 0.88);
      --border: rgba(0, 242, 254, 0.38);
      --accent: #00f2fe;
      --gold: #fbbf24;
      --text-primary: #ffffff;
      --text-muted: #94a3b8;
    }

    body {
      background: var(--bg-primary);
      color: var(--text-primary);
      font-family: 'Outfit', sans-serif;
      min-height: 100vh;
      padding: 24px 16px;
    }

    .wrap {
      max-width: 1320px;
      margin: 0 auto;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 1px dashed var(--border);
    }

    .title {
      font-family: 'Space Grotesk', sans-serif;
      font-size: 24px;
      font-weight: 800;
      color: var(--accent);
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 28px;
    }

    .stat-card {
      background: var(--bg-panel);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 18px;
      text-align: center;
    }

    .stat-card .v {
      font-size: 28px;
      font-weight: 900;
      margin-top: 4px;
    }

    .table-wrap {
      background: var(--bg-panel);
      border: 1.5px solid var(--border);
      border-radius: 20px;
      overflow-x: auto;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 13.5px;
    }

    th,
    td {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      vertical-align: middle;
    }

    th {
      background: rgba(0, 242, 254, 0.08);
      color: var(--accent);
      font-weight: 800;
      text-transform: uppercase;
      font-size: 11.5px;
      letter-spacing: 0.05em;
    }

    tr:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
    }

    .badge-paid {
      background: rgba(0, 230, 118, 0.2);
      color: #00e676;
      border: 1px solid #00e676;
    }

    .badge-pending {
      background: rgba(251, 191, 36, 0.2);
      color: #fbbf24;
      border: 1px solid #fbbf24;
    }

    .btn {
      padding: 6px 12px;
      border-radius: 6px;
      border: none;
      font-size: 11.5px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
      margin-right: 4px;
      margin-bottom: 4px;
    }

    .btn-approve {
      background: #00e676;
      color: #030712;
    }

    .btn-view {
      background: rgba(0, 242, 254, 0.2);
      color: #00f2fe;
      border: 1px solid #00f2fe;
    }

    .btn-card {
      background: linear-gradient(135deg, #fbbf24, #f59e0b);
      color: #030712;
      font-weight: 800;
    }

    .btn-delete {
      background: rgba(239, 68, 68, 0.2);
      color: #ef4444;
      border: 1px solid #ef4444;
    }

    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.85);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .modal-content {
      background: var(--bg-primary);
      border: 2px solid var(--accent);
      border-radius: 20px;
      padding: 24px;
      max-width: 540px;
      width: 100%;
      text-align: center;
      max-height: 90vh;
      overflow-y: auto;
    }

    /* Laptop / Desktop Responsive Enhancements (>= 1024px) */
    @media (min-width: 1024px) {
      .header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
      }

      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
      }

      .table-wrap {
        overflow-x: auto;
      }
    }

    /* Mobile Responsive Enhancements (<= 768px) - According to Laptop View */
    @media (max-width: 768px) {
      body {
        padding: 12px 8px;
      }

      .header {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        text-align: left;
        margin-bottom: 18px;
        padding-bottom: 14px;
      }

      .header .title {
        font-size: 18px;
        line-height: 1.25;
      }

      .header>div:last-child {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 8px !important;
        width: 100% !important;
        flex-wrap: nowrap !important;
      }

      .header a.btn {
        flex: 1 1 0 !important;
        width: auto !important;
        font-size: 11.5px !important;
        padding: 10px 8px !important;
        text-align: center !important;
        justify-content: center !important;
        white-space: nowrap !important;
        min-height: 40px !important;
        display: inline-flex !important;
        align-items: center !important;
        border-radius: 8px !important;
      }

      /* Bank Account Banner on Mobile: Side-by-side details */
      .bank-banner-wrap {
        padding: 14px 12px !important;
        border-radius: 14px !important;
        gap: 10px !important;
      }

      .bank-pills-wrap {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
        width: 100% !important;
      }

      .bank-pills-wrap>div {
        padding: 6px 8px !important;
        text-align: center !important;
        border-radius: 8px !important;
      }

      .bank-pills-wrap div[style*="font-size:15px"] {
        font-size: 11.5px !important;
      }

      /* Stats Grid: 2-Column Side-by-Side Grid matching laptop layout, NEVER 1-column */
      .stats-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
        margin-bottom: 20px !important;
      }

      .stat-card {
        padding: 12px 8px !important;
        border-radius: 14px !important;
        text-align: center !important;
      }

      .stat-card .v {
        font-size: 22px !important;
        margin-top: 4px !important;
      }

      /* Table Container */
      .table-wrap {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        border-radius: 14px !important;
        margin-bottom: 20px !important;
      }

      table {
        min-width: 850px !important;
        font-size: 12px !important;
      }

      th,
      td {
        padding: 10px 10px !important;
      }

      .modal-content {
        max-width: 500px !important;
        padding: 16px 12px !important;
        margin: 10px auto !important;
        max-height: 92vh !important;
      }

      .card-scroll-wrapper {
        width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        display: flex !important;
        justify-content: center !important;
        padding: 4px 0 10px !important;
      }

      #printableIdCard {
        width: 440px !important;
        min-width: 440px !important;
        max-width: 440px !important;
        box-sizing: border-box !important;
        margin: 0 auto !important;
        transform-origin: top center;
      }
    }

    @media (max-width: 480px) {
      .title {
        font-size: 16px !important;
      }

      /* Scales card proportionally on narrow mobile screens if desired while preserving exact laptop layout */
      .card-scroll-wrapper {
        justify-content: flex-start !important;
        padding-left: 2px !important;
      }
    }

    @media print {
      @page {
        size: A4 portrait;
        margin: 10mm;
      }

      html,
      body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }

      /* Hide dashboard and wrap entirely during print */
      .wrap,
      header,
      .header,
      .stats-grid,
      .table-wrap,
      .bank-banner-wrap {
        display: none !important;
      }

      body>*:not(.modal-overlay) {
        display: none !important;
      }

      .modal-overlay {
        display: block !important;
        position: static !important;
        width: 100% !important;
        height: auto !important;
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      .modal-content {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 auto !important;
        max-width: 100% !important;
        max-height: none !important;
        overflow: visible !important;
      }

      .modal-overlay button,
      #idCardModal h3,
      .id-card-btn-bar,
      .no-print,
      [onclick*="closeIdCardModal"] {
        display: none !important;
      }

      .card-scroll-wrapper {
        overflow: visible !important;
        display: block !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      #printableIdCard {
        display: block !important;
        position: relative !important;
        left: auto !important;
        top: auto !important;
        transform: none !important;
        margin: 10px auto !important;
        width: 440px !important;
        max-width: 440px !important;
        background: #04091a !important;
        border: 2.5px solid #00f2fe !important;
        border-radius: 24px !important;
        box-shadow: none !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      #printableIdCard * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>

<body>
  <div class="wrap">

    <?php if (!$isLoggedIn): ?>
      <div
        style="max-width:400px; margin:80px auto; background:var(--bg-panel); border:1.5px solid var(--border); border-radius:24px; padding:32px 24px; text-align:center; box-shadow:0 15px 40px rgba(0,0,0,0.6);">
        <div style="font-size:42px; margin-bottom:12px;">🛡️</div>
        <h2
          style="font-family:'Space Grotesk',sans-serif; color:var(--accent); font-size:22px; font-weight:800; margin-bottom:6px;">
          ORGANIZER LOGIN</h2>
        <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">Enter your admin passcode to access
          InnoWave-2k26 live registrations & print official delegate cards.</p>

        <?php if ($authError): ?>
          <div
            style="background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#ef4444; padding:10px; border-radius:8px; font-size:12.5px; margin-bottom:16px; font-weight:700;">
            ⚠️ <?= esc($authError) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="admin.php">
          <input type="hidden" name="action" value="login">
          <input type="password" name="password" placeholder="Enter Admin Passcode" required
            style="width:100%; background:var(--bg-primary); border:1.5px solid var(--border); border-radius:10px; padding:12px; color:#ffffff; font-size:15px; outline:none; margin-bottom:16px; text-align:center;">
          <button type="submit"
            style="width:100%; background:linear-gradient(135deg, var(--accent), #00a8ff); color:#030712; font-weight:900; padding:12px; border-radius:10px; border:none; font-size:15px; cursor:pointer;">
            🔓 ACCESS ADMIN DASHBOARD
          </button>
        </form>
      </div>
    <?php else: ?>

      <div class="header">
        <div>
          <div class="title">⚡ INNOWAVE-2K26 ORGANIZER ADMIN & ID CARD PRINTER</div>
          <div style="color:var(--text-muted); font-size:13px; margin-top:2px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <span>Live MySQL Registration Control & Official Delegate ID Card Printing Portal</span>
            <?php if (isset($pdo) && $pdo): ?>
              <span style="background:rgba(0,230,118,0.15); border:1px solid #00e676; color:#00e676; font-size:11px; font-weight:800; padding:2px 8px; border-radius:12px; display:inline-flex; align-items:center; gap:4px;">
                ● Pure MySQL Connected (<?= intval($totalRegs) ?> Records)
              </span>
            <?php else: ?>
              <span style="background:rgba(239,68,68,0.15); border:1px solid #ef4444; color:#ef4444; font-size:11px; font-weight:800; padding:2px 8px; border-radius:12px; display:inline-flex; align-items:center; gap:4px;">
                ● MySQL Connection Offline
              </span>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <a href="api/db-probe.php" target="_blank" class="btn" style="text-decoration:none; padding:10px 14px; font-size:12px; background:rgba(0,242,254,0.1); border:1px solid var(--accent); color:var(--accent);">
            🔍 MySQL Probe
          </a>
          <a href="api/admin.php?action=export-php&password=innowave2k26" class="btn btn-card"
            style="text-decoration:none; padding:10px 16px; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
            📊 📥 DOWNLOAD PHP ADMIN EXCEL (.CSV)
          </a>
          <a href="admin.php?action=logout" class="btn btn-delete" style="text-decoration:none; padding:10px 16px;">🔒
            Logout</a>
        </div>
      </div>

      <?php if (!isset($pdo) || !$pdo): ?>
        <div style="background:rgba(239,68,68,0.18); border:2px solid #ef4444; border-radius:14px; padding:16px 20px; margin-bottom:24px; color:#fca5a5;">
          <div style="font-weight:800; font-size:15px; color:#ef4444; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
            ⚠️ MySQL Database Connection Offline
          </div>
          <div style="font-size:13px; margin-bottom:10px; color:#ffffff;">
            The admin portal requires a live MySQL database. MySQL could not connect with the current credentials:
          </div>
          <code style="display:block; background:rgba(0,0,0,0.5); padding:10px 14px; border-radius:8px; font-family:monospace; color:#fca5a5; font-size:12.5px; word-break:break-all;">
            <?= htmlspecialchars($lastMysqlError ?: 'Unable to connect to MySQL host.') ?>
          </code>
          <div style="margin-top:12px; font-size:12.5px; color:#cbd5e1;">
            👉 <strong>How to resolve:</strong> Check your database credentials in <code>public/api/config.php</code> (or set <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>). You can also view the <a href="api/db-probe.php" target="_blank" style="color:#00f2fe; text-decoration:underline; font-weight:700;">Diagnostic Probe</a> for real-time connection status.
          </div>
        </div>
      <?php endif; ?>

      <!-- Official Bank Account Banner -->
      <div class="bank-banner-wrap"
        style="background:linear-gradient(135deg, rgba(0, 242, 254, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%); border:1.5px solid var(--accent); border-radius:16px; padding:16px 20px; margin-bottom:24px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
        <div>
          <div
            style="font-size:11px; font-weight:800; color:var(--accent); text-transform:uppercase; letter-spacing:0.08em;">
            OFFICIAL BENEFICIARY ACCOUNT</div>
          <div
            style="font-family:'Space Grotesk',sans-serif; font-size:15px; font-weight:800; color:#ffffff; margin-top:2px;">
            POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE</div>
        </div>
        <div class="bank-pills-wrap" style="display:flex; gap:16px;">
          <div
            style="background:rgba(255,255,255,0.06); padding:8px 14px; border-radius:10px; border:1px solid rgba(255,255,255,0.15);">
            <div style="color:#94a3b8; font-size:10px; font-weight:700;">ACCOUNT NO</div>
            <div style="color:#ffffff; font-size:15px; font-weight:900; font-family:monospace;">1414155000131347</div>
          </div>
          <div
            style="background:rgba(255,255,255,0.06); padding:8px 14px; border-radius:10px; border:1px solid rgba(255,255,255,0.15);">
            <div style="color:#94a3b8; font-size:10px; font-weight:700;">IFSC CODE</div>
            <div style="color:#00f2fe; font-size:15px; font-weight:900; font-family:monospace;">KVBL0001414</div>
          </div>
        </div>
      </div>

      <!-- Live Database Status Indicator -->
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:18px;">
        <div style="background:rgba(0,242,254,0.08); border:1.5px solid rgba(0,242,254,0.3); border-radius:12px; padding:8px 16px; display:inline-flex; align-items:center; gap:12px; font-size:12.5px;">
          <?php if (isset($pdo) && $pdo): ?>
            <span style="color:#00e676; font-weight:900;">● Database Connected</span>
            <span style="color:#94a3b8;">Engine: <b style="color:#ffffff; text-transform:uppercase;"><?= htmlspecialchars($activeEngine ?? 'mysql') ?></b></span>
            <span style="color:#94a3b8;">Total Live Records: <b style="color:#00f2fe;"><?= count($allRows) ?></b></span>
          <?php else: ?>
            <span style="color:#ef4444; font-weight:900;">❌ Database Offline</span>
            <span style="color:#fca5a5;"><?= htmlspecialchars($lastMysqlError ?: 'Connection failed') ?></span>
          <?php endif; ?>
        </div>
        <div style="color:var(--text-muted); font-size:12px;">
          Auto-synced with MySQL &bull; Auto-refresh enabled
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div style="color:#94a3b8; font-size:12px; font-weight:700;">TOTAL PARTICIPANTS</div>
          <div class="v"><?= count($allRows) ?></div>
        </div>
        <div class="stat-card">
          <div style="color:#94a3b8; font-size:12px; font-weight:700;">IEEE MEMBERS</div>
          <div class="v" style="color:#00f2fe"><?= $ieeeCount ?></div>
        </div>
        <div class="stat-card">
          <div style="color:#94a3b8; font-size:12px; font-weight:700;">NON-IEEE MEMBERS</div>
          <div class="v" style="color:#fbbf24"><?= $nonIeeeCount ?></div>
        </div>
        <div class="stat-card">
          <div style="color:#94a3b8; font-size:12px; font-weight:700;">VERIFIED COLLECTED MONEY</div>
          <div class="v" style="color:#00e676">₹<?= number_format($totalAmountCollected) ?></div>
        </div>
      </div>

      <!-- Registration View Filter Tabs -->
      <div
        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <a href="admin.php?view=all"
            style="text-decoration:none; padding:8px 16px; border-radius:10px; font-size:12.5px; font-weight:800; display:inline-flex; align-items:center; gap:6px; <?= ($viewFilter === 'all') ? 'background:rgba(0, 242, 254, 0.2); border:1.5px solid var(--accent); color:var(--accent);' : 'background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:var(--text-muted);' ?>">
            📋 All Registrations (<?= count($allRows) ?>)
          </a>
          <a href="admin.php?view=completed"
            style="text-decoration:none; padding:8px 16px; border-radius:10px; font-size:12.5px; font-weight:800; display:inline-flex; align-items:center; gap:6px; <?= ($viewFilter === 'completed') ? 'background:rgba(0, 230, 118, 0.2); border:1.5px solid #00e676; color:#00e676;' : 'background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:var(--text-muted);' ?>">
            🖼️ With Payment Screenshot (<?= $completedCount ?>)
          </a>
          <a href="admin.php?view=incomplete"
            style="text-decoration:none; padding:8px 16px; border-radius:10px; font-size:12.5px; font-weight:800; display:inline-flex; align-items:center; gap:6px; <?= ($viewFilter === 'incomplete') ? 'background:rgba(245, 158, 11, 0.2); border:1.5px solid #f59e0b; color:#f59e0b;' : 'background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:var(--text-muted);' ?>">
            ⏳ Pending Screenshot (<?= $incompleteCount ?>)
          </a>
        </div>
        <div style="color:var(--text-muted); font-size:12px; font-weight:600;">
          <?= ($viewFilter === 'incomplete') ? '⚠️ Showing pending registrations without payment screenshot' : (($viewFilter === 'completed') ? '✅ Showing registrations with uploaded payment screenshot' : '📋 Showing ALL live registrations in chronological order') ?>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Participant ID</th>
              <th>Status & Fee</th>
              <th>Leader / Contact</th>
              <th>College & Branch</th>
              <th>IEEE Info</th>
              <th>Submitted UTR / Proofs</th>
              <th>Organizer Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">
                  🚫 No registrations found in database yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <?php
                $st = $r['payment_status'] ?? 'Pending Payment Confirmation';
                $isPaid = ($st === 'Paid' || $st === 'Confirmed');
                $utr = $r['payment_ref'] ?? '';
                $hasIeeeCard = !empty($r['has_ieee_card']) || !empty($r['ieee_card']);
                $hasProof = !empty($r['has_payment_screenshot']) || !empty($r['payment_screenshot']) || !empty($r['payment_proof']);
                $proofImg = !empty($r['proof_url']) ? $r['proof_url'] : (!empty($r['payment_screenshot']) ? $r['payment_screenshot'] : ($r['payment_proof'] ?? ''));
                $ieeeCardImg = !empty($r['ieee_card_url']) ? $r['ieee_card_url'] : ($r['ieee_card'] ?? '');
                $teamIdEsc = esc($r['team_id'] ?? ('IW26-' . $r['id']));
                $nameEsc = esc($r['leader_name'] ?? 'Participant');
                $collegeEsc = esc($r['college_name'] ?? 'PSCMR CET');
                $branchEsc = esc($r['branch'] ?? '');
                $yearEsc = esc($r['year'] ?? '');
                $rollEsc = esc($r['roll_no'] ?? 'N/A');
                $ieeeIdEsc = esc($r['ieee_id'] ?? '');
                $ieeeEsc = ($r['ieee_member'] === 'Yes') ? 'IEEE Member' : 'Non-IEEE';
                $isPscmr = (stripos($collegeEsc, 'pscmr') !== false || stripos($collegeEsc, 'chalavadi') !== false || stripos($collegeEsc, 'potti sriramulu') !== false);
                ?>
                <tr id="row-<?= $r['id'] ?>">
                  <td><strong style="color:#00f2fe"><?= $teamIdEsc ?></strong></td>
                  <td>
                    <?php if ($isPaid): ?>
                      <span class="badge badge-paid">🟢 PAID & VERIFIED</span>
                    <?php else: ?>
                      <span class="badge badge-pending">⏳ PENDING</span>
                    <?php endif; ?>
                    <div style="display:flex; align-items:center; gap:6px; margin-top:5px; flex-wrap:wrap;">
                      <span style="font-weight:900; font-size:14px; color:#00e676;">₹<?= esc($r['amount'] ?? 100) ?></span>
                      <button type="button" class="btn btn-view"
                        style="padding:2px 7px; font-size:10px; font-weight:800; border-radius:5px; cursor:pointer;"
                        onclick="openAmountModal(<?= $r['id'] ?>, '<?= $teamIdEsc ?>', '<?= $nameEsc ?>', <?= intval($r['amount'] ?? 100) ?>)">✏️
                        Edit</button>
                    </div>
                  </td>
                  <td>
                    <strong><?= $nameEsc ?></strong><br>
                    <a href="mailto:<?= esc($r['leader_email']) ?>"
                      style="color:#00f2fe; text-decoration:none; font-size:12px;"><?= esc($r['leader_email']) ?></a>
                    <?php
                    $cleanEm = strtolower(trim($r['leader_email'] ?? ''));
                    $isClonedEmail = (!empty($cleanEm) && isset($emailCounts[$cleanEm]) && $emailCounts[$cleanEm] > 1);
                    ?>
                    <?php if ($isClonedEmail): ?>
                      <br><span
                        style="color:#ef4444; font-weight:900; font-size:10px; background:rgba(239,68,68,0.15); border:1px solid #ef4444; padding:1px 6px; border-radius:4px; display:inline-block; margin-top:2px;">
                        🚨 DUPLICATE EMAIL (<?= $emailCounts[$cleanEm] ?>x)
                      </span>
                    <?php endif; ?>
                    <br><span style="color:#94a3b8; font-size:12px;">📱 <?= esc($r['leader_phone']) ?></span>
                  </td>
                  <td>
                    <strong><?= $collegeEsc ?></strong>
                    <?php
                    $rollRaw = strtoupper(trim($r['roll_no'] ?? ''));
                    $isClonedRoll = (!empty($rollRaw) && strlen($rollRaw) >= 6 && $rollRaw !== 'N/A' && $rollRaw !== '-' && isset($rollCounts[$rollRaw]) && $rollCounts[$rollRaw] > 1);
                    ?>
                    <?php if ($isPscmr && !empty($r['roll_no']) && $rollEsc !== 'N/A' && $rollEsc !== '-'): ?>
                      <br><span
                        style="font-size:11px; font-weight:800; color:#00e676; background:rgba(0,230,118,0.12); border:1px solid rgba(0,230,118,0.3); padding:2px 7px; border-radius:5px; display:inline-block; margin-top:2px;">🎓
                        Adm No: <?= $rollEsc ?></span>
                      <?php if ($isClonedRoll): ?>
                        <br><span
                          style="color:#ef4444; font-weight:900; font-size:10px; background:rgba(239,68,68,0.15); border:1px solid #ef4444; padding:1px 6px; border-radius:4px; display:inline-block; margin-top:2px;">🚨
                          DUPLICATE ROLL (<?= $rollCounts[$rollRaw] ?>x)</span>
                      <?php endif; ?>
                    <?php endif; ?>
                    <br><span style="color:#94a3b8; font-size:12px;"><?= $branchEsc ?> (<?= $yearEsc ?>)</span>
                  </td>
                  <td>
                    <?= ($r['ieee_member'] === 'Yes') ? '<span style="color:#00f2fe; font-weight:800;">✓ IEEE Member</span>' : '<span style="color:#94a3b8;">Non-IEEE</span>' ?>
                    <?php if (!empty($ieeeIdEsc)): ?>
                      <div style="margin-top:2px;">
                        <span
                          style="font-size:11px; font-weight:800; color:#fbbf24; background:rgba(251, 191, 36, 0.12); border:1px solid rgba(251, 191, 36, 0.3); padding:1px 6px; border-radius:4px; display:inline-block;">
                          ⚡ IEEE ID: <?= $ieeeIdEsc ?>
                        </span>
                      </div>
                    <?php endif; ?>
                    <?php if ($hasIeeeCard): ?>
                      <div
                        style="margin-top:6px; background:rgba(0, 242, 254, 0.05); border:1.5px solid rgba(0, 242, 254, 0.3); border-radius:8px; padding:6px; max-width:240px;">
                        <div
                          style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; gap:4px; flex-wrap:wrap;">
                          <span
                            style="font-size:11px; font-weight:900; color:#00f2fe; background:rgba(0, 242, 254, 0.15); border:1px solid rgba(0, 242, 254, 0.35); padding:1px 6px; border-radius:4px; letter-spacing:0.04em;">
                            🆔 <?= $teamIdEsc ?>
                          </span>
                          <?php if (!empty($ieeeIdEsc)): ?>
                            <span style="font-size:10px; font-weight:800; color:#fbbf24;">
                              ⚡ <?= $ieeeIdEsc ?>
                            </span>
                          <?php endif; ?>
                        </div>
                        <div
                          style="position:relative; cursor:pointer; overflow:hidden; border-radius:6px; border:1px solid rgba(255,255,255,0.18); max-height:85px; margin-bottom:6px; background:#000;"
                          title="Click to view full IEEE card with ID <?= $teamIdEsc ?>"
                          onclick="viewImage('🪪 IEEE Membership Card Proof', '<?= esc($ieeeCardImg) ?>', '<?= $teamIdEsc ?>_<?= preg_replace('/[^A-Za-z0-9]/', '_', $nameEsc) ?>_ieee_<?= $ieeeIdEsc ?>.png', '<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '', '<?= $ieeeIdEsc ?>')">
                          <img src="<?= esc($ieeeCardImg) ?>" style="width:100%; height:75px; object-fit:cover; display:block;"
                            alt="IEEE Card ID <?= $teamIdEsc ?>">
                          <div
                            style="position:absolute; bottom:0; left:0; right:0; background:linear-gradient(transparent, rgba(0,0,0,0.9)); padding:2px 4px; font-size:9.5px; color:#fff; font-weight:700; display:flex; justify-content:space-between;">
                            <span>🔍 View Card</span>
                            <span
                              style="color:#00f2fe; font-family:monospace;"><?= !empty($ieeeIdEsc) ? $ieeeIdEsc : $teamIdEsc ?></span>
                          </div>
                        </div>
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                          <button type="button" class="btn btn-view" style="font-size:10px; padding:4px 6px; flex:1;"
                            onclick="viewImage('🪪 IEEE Membership Card Proof', '<?= esc($ieeeCardImg) ?>', '<?= $teamIdEsc ?>_<?= preg_replace('/[^A-Za-z0-9]/', '_', $nameEsc) ?>_ieee_<?= $ieeeIdEsc ?>.png', '<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '', '<?= $ieeeIdEsc ?>')">
                            👁️ View
                          </button>
                          <button type="button" class="btn btn-approve"
                            style="font-size:10px; padding:4px 6px; font-weight:800; background:rgba(0,230,118,0.18); border:1px solid #00e676; color:#00e676; border-radius:5px; cursor:pointer; flex:1.3;"
                            title="Download PNG image with ID <?= $teamIdEsc ?> & IEEE ID <?= $ieeeIdEsc ?> stamped"
                            onclick="downloadScreenshot('<?= esc($ieeeCardImg) ?>', '<?= $teamIdEsc ?>_<?= preg_replace('/[^A-Za-z0-9]/', '_', $nameEsc) ?>_ieee_<?= $ieeeIdEsc ?>.png', '<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '', '<?= $ieeeIdEsc ?>')">
                            📥 Download PNG
                          </button>
                        </div>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                    $cleanRefUpper = strtoupper(preg_replace('/[^A-Z0-9]/', '', $utr));
                    $isClonedUtr = (!empty($cleanRefUpper) && strlen($cleanRefUpper) >= 6 && !in_array($cleanRefUpper, $ignoreRefs) && isset($utrCounts[$cleanRefUpper]) && $utrCounts[$cleanRefUpper] > 1);
                    ?>
                    <?php if (!empty($utr)): ?>
                      <div style="font-family:monospace; color:#00e676; font-weight:800; font-size:13px;">REF: <?= esc($utr) ?>
                      </div>
                      <?php if (!empty($r['payment_mode']) && $r['payment_mode'] !== 'Bank Transfer'): ?>
                        <div style="margin-top:2px;">
                          <span
                            style="font-size:10.5px; font-weight:700; color:#00f2fe; background:rgba(0,242,254,0.1); border:1px solid rgba(0,242,254,0.25); padding:1px 6px; border-radius:4px; display:inline-block;">
                            📱 <?= esc($r['payment_mode']) ?>
                          </span>
                        </div>
                      <?php endif; ?>
                      <?php if ($isClonedUtr): ?>
                        <div
                          style="color:#ef4444; font-weight:900; font-size:11px; margin-top:2px; background:rgba(239,68,68,0.15); border:1px solid #ef4444; padding:2px 6px; border-radius:4px; display:inline-block;">
                          🚨 CLONED UTR (<?= $utrCounts[$cleanRefUpper] ?> Duplicate Submissions)
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span style="color:#94a3b8; font-size:12px;">No Ref submitted</span>
                    <?php endif; ?>
                    <?php if ($hasProof): ?>
                      <div
                        style="margin-top:6px; background:rgba(0, 242, 254, 0.05); border:1.5px solid rgba(0, 242, 254, 0.3); border-radius:8px; padding:6px; max-width:240px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                          <span
                            style="font-size:11px; font-weight:900; color:#00f2fe; background:rgba(0, 242, 254, 0.15); border:1px solid rgba(0, 242, 254, 0.35); padding:1px 6px; border-radius:4px; letter-spacing:0.04em;">
                            🆔 <?= $teamIdEsc ?>
                          </span>
                          <span style="font-size:10px; font-weight:700; color:#00e676;">✓ Screenshot</span>
                        </div>
                        <div
                          style="position:relative; cursor:pointer; overflow:hidden; border-radius:6px; border:1px solid rgba(255,255,255,0.18); max-height:85px; margin-bottom:6px; background:#000;"
                          title="Click to view full screenshot for ID <?= $teamIdEsc ?>"
                          onclick="viewImage('🖼️ Payment Screenshot Proof', '<?= esc($proofImg) ?>', '<?= $teamIdEsc ?>_<?= preg_replace('/[^A-Za-z0-9]/', '_', $nameEsc) ?>_payment.png', '<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '<?= esc($utr) ?>')">
                          <img src="<?= esc($proofImg) ?>" style="width:100%; height:75px; object-fit:cover; display:block;"
                            alt="Payment Proof ID <?= $teamIdEsc ?>">
                          <div
                            style="position:absolute; bottom:0; left:0; right:0; background:linear-gradient(transparent, rgba(0,0,0,0.9)); padding:2px 4px; font-size:9.5px; color:#fff; font-weight:700; display:flex; justify-content:space-between;">
                            <span>🔍 View Proof</span>
                            <span style="color:#00f2fe; font-family:monospace;"><?= $teamIdEsc ?></span>
                          </div>
                        </div>
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                          <button type="button" class="btn btn-view" style="font-size:10px; padding:4px 6px; flex:1;"
                            onclick="viewImage('🖼️ Payment Screenshot Proof', '<?= esc($proofImg) ?>', '<?= $teamIdEsc ?>_<?= preg_replace('/[^A-Za-z0-9]/', '_', $nameEsc) ?>_payment.png', '<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '<?= esc($utr) ?>')">
                            👁️ View
                          </button>
                          <button type="button" class="btn btn-approve"
                            style="font-size:10px; padding:4px 6px; font-weight:800; background:rgba(0,230,118,0.18); border:1px solid #00e676; color:#00e676; border-radius:5px; cursor:pointer; flex:1.3;"
                            title="Download PNG image with ID <?= $teamIdEsc ?> stamped"
                            onclick="downloadScreenshot('<?= esc($proofImg) ?>', '<?= $teamIdEsc ?>_<?= preg_replace('/[^A-Za-z0-9]/', '_', $nameEsc) ?>_payment.png', '<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '<?= esc($utr) ?>')">
                            📥 Download PNG
                          </button>
                        </div>
                      </div>
                    <?php else: ?>
                      <div style="margin-top:4px; font-size:11px; color:#94a3b8;">
                        <span style="color:#64748b;">(No screenshot for ID <strong
                            style="color:#cbd5e1;"><?= $teamIdEsc ?></strong>)</span>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn btn-card"
                      onclick="openOfficialIdCardModal('<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '<?= $branchEsc ?>', '<?= $yearEsc ?>', '<?= $rollEsc ?>', '<?= $ieeeEsc ?>')">🪪
                      PRINT ID CARD</button><br>
                    <div style="display:flex; gap:4px; margin-top:4px;">
                      <?php if (!$isPaid): ?>
                        <button class="btn btn-approve" style="flex:1;"
                          onclick="updateStatus(<?= $r['id'] ?>, 'approve_paid')">✓ Approve</button>
                      <?php else: ?>
                        <button class="btn btn-view" style="flex:1;" onclick="updateStatus(<?= $r['id'] ?>, 'mark_pending')">⏳
                          Pending</button>
                      <?php endif; ?>
                      <button class="btn btn-view" style="padding:4px 8px; font-size:11px; font-weight:700;"
                        title="Change participant fee amount"
                        onclick="openAmountModal(<?= $r['id'] ?>, '<?= $teamIdEsc ?>', '<?= $nameEsc ?>', <?= intval($r['amount'] ?? 100) ?>)">💰
                        Fee</button>
                      <?php $isClonedAny = ($isClonedUtr || $isClonedEmail || $isClonedRoll); ?>
                      <button class="btn btn-delete"
                        style="<?= $isClonedAny ? 'background:#dc2626; color:#fff; font-weight:bold; border:1px solid #ef4444;' : '' ?>"
                        title="<?= $isClonedAny ? 'Delete duplicate/cloned record' : 'Delete record' ?>"
                        onclick="updateStatus(<?= $r['id'] ?>, 'delete')"><?= $isClonedAny ? '🗑️' : '✕' ?></button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Image Viewer Modal -->
      <div class="modal-overlay" id="imgModal" onclick="closeImgModal()">
        <div class="modal-content" onclick="event.stopPropagation()"
          style="max-width:580px; background:var(--bg-panel); border:2px solid var(--accent); border-radius:20px; padding:20px; box-shadow:0 25px 60px rgba(0,0,0,0.8);">
          <div
            style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:12px; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
            <div>
              <h3 id="imgModalTitle"
                style="color:var(--accent); font-size:18px; margin:0; font-family:'Space Grotesk',sans-serif;">🖼️ PAYMENT
                SCREENSHOT PROOF</h3>
              <div style="font-size:12.5px; color:var(--text-muted); margin-top:2px;">
                Participant: <strong id="imgModalParticipantName" style="color:#ffffff;">-</strong>
              </div>
            </div>
            <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
              <div id="imgModalIdBadge"
                style="background:rgba(0,242,254,0.18); border:1.5px solid #00f2fe; color:#00f2fe; font-size:14px; font-weight:900; padding:5px 14px; border-radius:20px; font-family:'Space Grotesk',sans-serif; letter-spacing:0.04em;">
                🆔 IW26-0000
              </div>
              <div id="imgModalIeeeHeaderBadge"
                style="display:none; background:rgba(251,191,36,0.18); border:1.5px solid #fbbf24; color:#fbbf24; font-size:13px; font-weight:900; padding:5px 12px; border-radius:20px; font-family:'Space Grotesk',sans-serif; letter-spacing:0.04em;">
                ⚡ IEEE: <span id="imgModalIeeeHeaderVal">-</span>
              </div>
            </div>
          </div>
          <div id="imgModalMetaBar"
            style="background:rgba(0,0,0,0.35); border:1px solid var(--border); border-radius:10px; padding:8px 12px; font-size:12px; color:#cbd5e1; margin-bottom:14px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div>🏫 College: <span id="imgModalCollege" style="color:#ffffff; font-weight:700;">-</span></div>
            <div id="imgModalUtrBox">🔢 UTR / Ref: <code id="imgModalUtr" style="color:#00e676; font-weight:800;">-</code>
            </div>
            <div id="imgModalIeeeBox" style="display:none;">⚡ IEEE Membership ID: <code id="imgModalIeee"
                style="color:#fbbf24; font-weight:900; font-size:13px; font-family:monospace;">-</code></div>
          </div>
          <div
            style="background:#000; border-radius:12px; border:2px solid var(--accent); overflow:hidden; margin-bottom:14px; text-align:center;">
            <img id="imgModalSrc" src=""
              style="max-width:100%; max-height:65vh; display:block; margin:0 auto; object-fit:contain;">
          </div>
          <div style="display:flex; gap:10px; width:100%;">
            <button type="button" class="btn btn-approve" id="imgModalDownloadBtn"
              style="flex:1; padding:12px; font-size:14px; font-weight:900; border-radius:8px;"
              onclick="downloadModalImg()">
              📥 Download Screenshot along with ID (<span id="imgModalDownloadId">ID</span>) as PNG
            </button>
            <button type="button" class="btn btn-view" style="padding:12px 20px; font-size:14px; border-radius:8px;"
              onclick="closeImgModal()">Close</button>
          </div>
        </div>
      </div>

      <!-- Admin Participant Amount Change Modal -->
      <div class="modal-overlay" id="amountModal" onclick="closeAmountModal()">
        <div class="modal-content" onclick="event.stopPropagation()"
          style="max-width:420px; background:var(--bg-panel); border:2px solid var(--accent); border-radius:20px; padding:24px 20px; text-align:center;">
          <h3 style="color:var(--accent); font-size:18px; margin-bottom:6px; font-family:'Space Grotesk',sans-serif;">💰
            CHANGE PARTICIPANT AMOUNT</h3>
          <p style="color:var(--text-muted); font-size:13px; margin-bottom:16px;">
            Editing amount for <strong id="amountModalTeamId" style="color:#00f2fe;">IW26-0001</strong> (<span
              id="amountModalName" style="color:#ffffff;">Participant</span>)
          </p>

          <div
            style="background:rgba(0,242,254,0.06); border:1px solid var(--border); border-radius:12px; padding:14px; margin-bottom:18px; text-align:left;">
            <label
              style="font-size:12px; font-weight:800; color:var(--accent); text-transform:uppercase; display:block; margin-bottom:6px;">
              Fee Amount (₹)
            </label>
            <input type="number" id="amountModalInput" min="0" max="10000" step="10" placeholder="Enter amount (e.g. 100)"
              style="width:100%; background:var(--bg-primary); border:1.5px solid var(--border); border-radius:8px; padding:12px; color:#ffffff; font-size:18px; font-weight:900; outline:none; text-align:center;">

            <!-- Preset Quick Selection Buttons -->
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-top:12px;">
              <button type="button" class="btn"
                style="background:rgba(251, 191, 36, 0.15); border:1.5px solid #fbbf24; color:#fbbf24; padding:8px 4px; font-size:12px; font-weight:800; border-radius:6px; cursor:pointer;"
                onclick="setAmountPreset(50)">
                ₹50<br><span style="font-size:9px; font-weight:600;">PSCMR IEEE</span>
              </button>
              <button type="button" class="btn"
                style="background:rgba(0, 230, 118, 0.15); border:1.5px solid #00e676; color:#00e676; padding:8px 4px; font-size:12px; font-weight:800; border-radius:6px; cursor:pointer;"
                onclick="setAmountPreset(100)">
                ₹100<br><span style="font-size:9px; font-weight:600;">PSCMR / Other IEEE</span>
              </button>
              <button type="button" class="btn"
                style="background:rgba(0, 242, 254, 0.15); border:1.5px solid #00f2fe; color:#00f2fe; padding:8px 4px; font-size:12px; font-weight:800; border-radius:6px; cursor:pointer;"
                onclick="setAmountPreset(200)">
                ₹200<br><span style="font-size:9px; font-weight:600;">Other Non-IEEE</span>
              </button>
            </div>
          </div>

          <div style="display:flex; gap:10px;">
            <button type="button" class="btn btn-approve" id="amountModalSaveBtn"
              style="flex:1; padding:12px; font-size:14px; font-weight:800; border-radius:8px;"
              onclick="saveParticipantAmount()">💾 SAVE AMOUNT</button>
            <button type="button" class="btn btn-view" style="padding:12px 18px; border-radius:8px;"
              onclick="closeAmountModal()">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Admin Official ID Card Printer Modal -->
      <div class="modal-overlay" id="idCardModal" onclick="closeIdCardModal()">
        <div class="modal-content" onclick="event.stopPropagation()"
          style="max-width:500px; background:#020612; border:2px solid var(--accent); border-radius:24px; padding:20px 14px; text-align:center;">
          <h3 style="color:var(--gold); font-size:16px; margin-bottom:12px; font-family:'Space Grotesk',sans-serif;">🪪
            OFFICIAL PARTICIPANT ID CARD PRINTER</h3>

          <!-- Scroll Wrapper for Mobile View: preserves exact laptop width without squashing -->
          <div class="card-scroll-wrapper">
            <!-- Printable Card Container (Exact 1:1 Graphic Card Layout) -->
            <div id="printableIdCard"
              style="width:440px; min-width:440px; max-width:440px; margin:0 auto; background:#04091a; border:2.5px solid #00f2fe; border-radius:24px; padding:20px 16px; color:#ffffff; font-family:'Inter',sans-serif; box-sizing:border-box; position:relative; box-shadow:0 0 35px rgba(0,242,254,0.3); text-align:center;">

              <!-- TOP DARK NAVY BANNER HEADER -->
              <div style="text-align:center; margin-bottom:12px;">
                <!-- Gold Pill Badge -->
                <div id="cardGoldPill"
                  style="display:inline-block; background:rgba(251, 191, 36, 0.1); border:1.5px solid #fbbf24; color:#fbbf24; border-radius:20px; padding:4px 16px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">
                  👑 OFFICIAL DELEGATE PASS
                </div>

                <!-- Title -->
                <div
                  style="font-family:'Space Grotesk',sans-serif; font-size:26px; font-weight:900; color:#ffffff; letter-spacing:0.05em; line-height:1.1; margin-bottom:4px;">
                  INNOWAVE-2K26
                </div>

                <!-- Subtitle -->
                <div
                  style="font-size:11px; font-weight:800; color:#fbbf24; letter-spacing:0.08em; text-transform:uppercase;">
                  ENGINEER'S DAY CELEBRATION
                </div>
                <div
                  style="font-size:10px; font-weight:800; color:#38bdf8; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:10px;">
                  NATIONAL LEVEL FEST
                </div>

                <!-- Participant ID Cyan Pill Badge -->
                <div
                  style="display:inline-flex; align-items:center; justify-content:center; gap:6px; background:rgba(0, 242, 254, 0.1); border:1.5px solid #00f2fe; color:#00f2fe; border-radius:20px; padding:5px 18px; font-size:12px; font-weight:800; font-family:'Space Grotesk',monospace;">
                  <span
                    style="background:#00f2fe; color:#04091a; padding:1px 4px; border-radius:4px; font-size:10px; font-weight:900;">ID</span>
                  <span>PARTICIPANT ID: <span id="cardTeamId">IW26-0001</span></span>
                </div>
              </div>

              <!-- AMBER ACCENT DIVIDER LINE -->
              <div
                style="height:3px; background:linear-gradient(90deg, transparent, #fbbf24, transparent); margin-bottom:14px; border-radius:2px;">
              </div>

              <!-- MIDDLE BODY CONTAINER (White background, cyan border) -->
              <div
                style="background:#ffffff; border:2px solid #00f2fe; border-radius:16px; padding:12px; color:#0f172a; margin-bottom:14px; text-align:left;">

                <!-- 1. Registered Participant Name Box (Cream Yellow) -->
                <div
                  style="background:#fffbeb; border:1.5px solid #fef08a; border-radius:12px; padding:10px 14px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                  <div>
                    <div
                      style="color:#92400e; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                      <span>👤</span> REGISTERED PARTICIPANT NAME
                    </div>
                    <div id="cardName"
                      style="font-family:'Space Grotesk',sans-serif; font-size:20px; font-weight:900; color:#0f172a;">
                      Participant Name
                    </div>
                  </div>
                  <div
                    style="background:#041b2d; color:#00f2fe; border:1px solid #00f2fe; font-size:11px; font-weight:800; padding:4px 10px; border-radius:8px; font-family:monospace;">
                    ID: <span id="cardBadgeId">IW26-0001</span>
                  </div>
                </div>

                <!-- Category & Track Status Badges -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; gap:8px;">
                  <div id="cardCategory"
                    style="display:inline-block; background:rgba(0, 242, 254, 0.12); border:1px solid #00f2fe; color:#00f2fe; border-radius:12px; padding:3px 12px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em;">
                    ⚡ IEEE MEMBER
                  </div>
                  <div
                    style="color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">
                    TRACK: OPEN INNOVATION
                  </div>
                </div>

                <!-- 2. College / Institution Name Box -->
                <div
                  style="background:#ffffff; border:1px solid #e2e8f0; border-left:4px solid #0284c7; border-radius:10px; padding:8px 12px; margin-bottom:10px;">
                  <div
                    style="color:#0284c7; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                    <span>🏫</span> COLLEGE / INSTITUTION NAME
                  </div>
                  <div id="cardCollege" style="font-size:14px; font-weight:800; color:#0f172a;">
                    PSCMR CET
                  </div>
                </div>

                <!-- 3. Branch & Year of Study (with Admission No for PSCMR only) -->
                <div id="cardBranchYearGrid"
                  style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                  <div id="cardRollBox"
                    style="display:none; background:#ffffff; border:1px solid #a7f3d0; border-left:4px solid #059669; border-radius:10px; padding:8px 12px; text-align:left;">
                    <div
                      style="color:#059669; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                      <span>🎫</span> ADMISSION NO
                    </div>
                    <div id="cardRoll" style="font-size:14px; font-weight:800; color:#064e3b;">
                      -
                    </div>
                  </div>
                  <div
                    style="background:#ffffff; border:1px solid #e2e8f0; border-left:4px solid #0284c7; border-radius:10px; padding:8px 12px; text-align:left;">
                    <div
                      style="color:#0284c7; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                      <span>🎓</span> BRANCH
                    </div>
                    <div id="cardBranch" style="font-size:14px; font-weight:800; color:#0f172a;">
                      CSE
                    </div>
                  </div>
                  <div
                    style="background:#ffffff; border:1px solid #e2e8f0; border-left:4px solid #0284c7; border-radius:10px; padding:8px 12px; text-align:left;">
                    <div
                      style="color:#0284c7; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                      <span>📅</span> YEAR OF STUDY
                    </div>
                    <div id="cardYear" style="font-size:14px; font-weight:800; color:#0f172a;">
                      3rd Year
                    </div>
                  </div>
                </div>

                <!-- 4. Events Evaluation Checklist Table -->
                <div style="border:1px solid #cbd5e1; border-radius:12px; overflow:hidden;">
                  <div
                    style="background:#04091a; color:#ffffff; padding:8px 12px; display:flex; justify-content:space-between; align-items:center; font-size:11px; font-weight:800;">
                    <div style="display:flex; align-items:center; gap:6px;">
                      <span>📋</span> EVENTS EVALUATION CHECKLIST
                    </div>
                    <div style="color:#00f2fe; font-family:'Space Grotesk',sans-serif;">
                      INNOWAVE-2K26
                    </div>
                  </div>

                  <table
                    style="width:100%; border-collapse:collapse; font-size:11px; background:#ffffff; table-layout:fixed;">
                    <thead>
                      <tr
                        style="border-bottom:1px solid #e2e8f0; color:#475569; font-size:9.5px; font-weight:800; text-transform:uppercase;">
                        <th style="padding:6px 8px; text-align:left; width:52%;">BROCHURE EVENT NAME</th>
                        <th style="padding:6px 4px; text-align:center; width:18%;">MARK [✓]</th>
                        <th style="padding:6px 8px; text-align:right; width:30%;">HEAD SIGN</th>
                      </tr>
                    </thead>
                    <tbody style="color:#0f172a; font-weight:700;">
                      <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:6px 8px; font-size:11px;">🧠 Technical Quiz</td>
                        <td style="padding:6px 4px; text-align:center;">
                          <div
                            style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;">
                          </div>
                        </td>
                        <td style="padding:6px 8px; text-align:right;"><span
                            style="display:inline-block; width:75px; border-bottom:1.5px solid #cbd5e1;">&nbsp;</span>
                        </td>
                      </tr>
                      <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:6px 8px; font-size:11px;">💻 Coding Challenge</td>
                        <td style="padding:6px 4px; text-align:center;">
                          <div
                            style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;">
                          </div>
                        </td>
                        <td style="padding:6px 8px; text-align:right;"><span
                            style="display:inline-block; width:75px; border-bottom:1.5px solid #cbd5e1;">&nbsp;</span>
                        </td>
                      </tr>
                      <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:6px 8px; font-size:11px;">🗺️ Tech Treasure Hunt</td>
                        <td style="padding:6px 4px; text-align:center;">
                          <div
                            style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;">
                          </div>
                        </td>
                        <td style="padding:6px 8px; text-align:right;"><span
                            style="display:inline-block; width:75px; border-bottom:1.5px solid #cbd5e1;">&nbsp;</span>
                        </td>
                      </tr>
                      <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:6px 8px; font-size:11px;">🚀 Project Expo</td>
                        <td style="padding:6px 4px; text-align:center;">
                          <div
                            style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;">
                          </div>
                        </td>
                        <td style="padding:6px 8px; text-align:right;"><span
                            style="display:inline-block; width:75px; border-bottom:1.5px solid #cbd5e1;">&nbsp;</span>
                        </td>
                      </tr>
                      <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:6px 8px; font-size:11px;">🤖 Prompt Engineering</td>
                        <td style="padding:6px 4px; text-align:center;">
                          <div
                            style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;">
                          </div>
                        </td>
                        <td style="padding:6px 8px; text-align:right;"><span
                            style="display:inline-block; width:75px; border-bottom:1.5px solid #cbd5e1;">&nbsp;</span>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:6px 8px; font-size:11px;">🎬 Reels (1 Min)</td>
                        <td style="padding:6px 4px; text-align:center;">
                          <div
                            style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;">
                          </div>
                        </td>
                        <td style="padding:6px 8px; text-align:right;"><span
                            style="display:inline-block; width:75px; border-bottom:1.5px solid #cbd5e1;">&nbsp;</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              </div>

              <!-- BOTTOM DARK NAVY FOOTER SECTION -->
              <div style="display:flex; justify-content:space-between; align-items:flex-end; padding:4px 6px;">
                <!-- Left: QR Code & Verify Text -->
                <div style="display:flex; align-items:center; gap:10px;">
                  <div
                    style="background:#ffffff; padding:4px; border-radius:8px; border:1.5px solid #00f2fe; width:64px; height:64px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <img id="cardQrImg" src="" alt="QR Code" style="width:100%; height:100%; object-fit:contain;">
                  </div>
                  <div style="text-align:left;">
                    <div
                      style="color:#00f2fe; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">
                      SCAN TO VERIFY
                    </div>
                    <div style="color:#94a3b8; font-size:9.5px; font-weight:600;">
                      Official Pass
                    </div>
                    <div style="color:#64748b; font-size:9.5px; font-weight:600;">
                      InnoWave-2k26
                    </div>
                  </div>
                </div>

                <!-- Right: Signature Line -->
                <div style="text-align:right;">
                  <div style="display:inline-block; width:120px; border-bottom:1.5px solid #64748b; margin-bottom:4px;">
                    &nbsp;</div>
                  <div
                    style="color:#fbbf24; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">
                    REGISTRATION SIGNATURE
                  </div>
                </div>
              </div>

            </div>
          </div>

          <div style="display:flex; gap:10px; margin-top:16px;">
            <button class="btn btn-approve" style="flex:1; padding:12px; font-size:14px;" onclick="printIdCard()">🖨️
              PRINT THIS ID CARD</button>
            <button class="btn btn-view" style="padding:12px;" onclick="closeIdCardModal()">Close</button>
          </div>
        </div>
      </div>

      <script>
        let currentModalImgSrc = '';
        let currentModalImgName = 'screenshot.png';
        let currentModalParticipantId = '';
        let currentModalParticipantName = '';
        let currentModalCollege = '';
        let currentModalUtr = '';
        let currentModalIeeeId = '';

        function viewImage(title, src, filename, participantId, participantName, collegeName, utr, ieeeId) {
          currentModalImgSrc = src;
          currentModalParticipantId = participantId || '';
          currentModalParticipantName = participantName || '';
          currentModalCollege = collegeName || '';
          currentModalUtr = utr || '';
          currentModalIeeeId = ieeeId || '';
          currentModalImgName = filename || ((participantId ? participantId + '_' : '') + (ieeeId ? 'ieee_' + ieeeId + '_card.png' : 'payment_screenshot.png'));

          document.getElementById('imgModalTitle').textContent = title || 'IMAGE PROOF';
          document.getElementById('imgModalSrc').src = src;

          const idBadge = document.getElementById('imgModalIdBadge');
          if (idBadge) {
            idBadge.textContent = participantId ? '🆔 ' + participantId : '🆔 PARTICIPANT';
          }

          const ieeeHdrBadge = document.getElementById('imgModalIeeeHeaderBadge');
          const ieeeHdrVal = document.getElementById('imgModalIeeeHeaderVal');
          if (ieeeHdrBadge) {
            if (ieeeId) {
              ieeeHdrBadge.style.display = 'inline-block';
              if (ieeeHdrVal) ieeeHdrVal.textContent = ieeeId;
            } else {
              ieeeHdrBadge.style.display = 'none';
            }
          }

          const nameEl = document.getElementById('imgModalParticipantName');
          if (nameEl) nameEl.textContent = participantName || '-';
          const colEl = document.getElementById('imgModalCollege');
          if (colEl) colEl.textContent = collegeName || '-';

          const utrBox = document.getElementById('imgModalUtrBox');
          const utrEl = document.getElementById('imgModalUtr');
          const ieeeBox = document.getElementById('imgModalIeeeBox');
          const ieeeEl = document.getElementById('imgModalIeee');

          if (ieeeId) {
            if (utrBox) utrBox.style.display = 'none';
            if (ieeeBox) {
              ieeeBox.style.display = 'block';
              if (ieeeEl) ieeeEl.textContent = ieeeId;
            }
            const dlBtn = document.getElementById('imgModalDownloadBtn');
            if (dlBtn) {
              dlBtn.innerHTML = `📥 Download IEEE Card along with ID (<span id="imgModalDownloadId">${participantId || 'ID'}</span>) & IEEE No (<b>${ieeeId}</b>) as PNG`;
            }
          } else {
            if (ieeeBox) ieeeBox.style.display = 'none';
            if (utrBox) {
              utrBox.style.display = 'block';
              if (utrEl) utrEl.textContent = utr || 'N/A';
            }
            const dlBtn = document.getElementById('imgModalDownloadBtn');
            if (dlBtn) {
              dlBtn.innerHTML = `📥 Download Screenshot along with ID (<span id="imgModalDownloadId">${participantId || 'ID'}</span>) as PNG`;
            }
          }

          document.getElementById('imgModal').style.display = 'flex';
        }

        function closeImgModal() {
          document.getElementById('imgModal').style.display = 'none';
          currentModalImgSrc = '';
          currentModalIeeeId = '';
        }

        function downloadModalImg() {
          if (!currentModalImgSrc) {
            alert('No image loaded to download.');
            return;
          }
          downloadScreenshot(currentModalImgSrc, currentModalImgName, currentModalParticipantId, currentModalParticipantName, currentModalCollege, currentModalUtr, currentModalIeeeId);
        }

        function downloadScreenshot(src, filename, participantId, participantName, collegeName, utr, ieeeId) {
          if (!src) {
            alert('No screenshot image found to download.');
            return;
          }

          const safeId = String(participantId || '').trim();
          const safeName = String(participantName || '').replace(/[^A-Za-z0-9]/g, '_').trim();
          const safeIeee = String(ieeeId || '').trim();

          let outName = filename || '';
          if (!outName) {
            outName = (safeId ? safeId + '_' : '') + (safeName ? safeName + '_' : '') + (safeIeee ? 'ieee_' + safeIeee + '_' : '') + (safeIeee ? 'ieee_card.png' : 'payment_screenshot.png');
          }
          if (!outName.toLowerCase().endsWith('.png')) {
            outName += '.png';
          }
          if (safeId && !outName.startsWith(safeId)) {
            outName = safeId + '_' + outName;
          }

          // Load image and composite ID header banner onto PNG
          const img = new Image();
          img.crossOrigin = 'anonymous';
          img.onload = function () {
            try {
              const canvas = document.createElement('canvas');
              const w = img.naturalWidth || img.width || 800;
              const h = img.naturalHeight || img.height || 1000;

              // Compute top ID watermark banner height proportional to image width
              const bannerHeight = safeId ? Math.max(90, Math.round(w * 0.12)) : 0;
              canvas.width = w;
              canvas.height = h + bannerHeight;
              const ctx = canvas.getContext('2d');

              if (safeId && bannerHeight > 0) {
                // Dark navy blueprint gradient background
                const grad = ctx.createLinearGradient(0, 0, w, bannerHeight);
                grad.addColorStop(0, '#04091a');
                grad.addColorStop(1, '#0b193d');
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, w, bannerHeight);

                // Cyan accent separator line
                ctx.fillStyle = '#00f2fe';
                ctx.fillRect(0, bannerHeight - 4, w, 4);

                const titleFontSize = Math.max(16, Math.round(bannerHeight * 0.28));
                const subFontSize = Math.max(11, Math.round(bannerHeight * 0.17));
                const marginX = Math.max(16, Math.round(w * 0.035));

                // Line 1 Left: Event Title
                ctx.font = 'bold ' + titleFontSize + 'px sans-serif';
                ctx.fillStyle = '#00f2fe';
                ctx.textBaseline = 'middle';
                ctx.fillText('INNOWAVE-2K26', marginX, bannerHeight * 0.32);

                // Line 1 Right: Prominent Participant ID + IEEE Membership ID
                let idText = 'PARTICIPANT ID: ' + safeId;
                if (safeIeee) {
                  idText += '   |   IEEE ID: ' + safeIeee;
                }
                ctx.font = 'bold ' + (titleFontSize + 1) + 'px sans-serif';
                const idWidth = ctx.measureText(idText).width;
                ctx.fillStyle = safeIeee ? '#fbbf24' : '#00e676';
                ctx.fillText(idText, Math.max(marginX, w - idWidth - marginX), bannerHeight * 0.32);

                // Line 2: Participant Name & College
                ctx.font = '600 ' + subFontSize + 'px sans-serif';
                ctx.fillStyle = '#ffffff';
                let line2 = (safeName ? 'Participant: ' + safeName.replace(/_/g, ' ') : '') + (collegeName ? '  |  College: ' + collegeName : '');
                if (!line2) line2 = 'Participant Details Verified';
                ctx.fillText(line2, marginX, bannerHeight * 0.62);

                // Line 3: UTR or IEEE Membership Details
                ctx.font = '500 ' + (subFontSize - 1) + 'px sans-serif';
                let line3 = '';
                if (safeIeee) {
                  ctx.fillStyle = '#38bdf8';
                  line3 = '⚡ OFFICIAL IEEE MEMBERSHIP ID: ' + safeIeee + '  •  PSCMRCET IEEE Student Branch Verification';
                } else {
                  ctx.fillStyle = '#fbbf24';
                  line3 = (utr ? 'UPI Ref / UTR: ' + utr : 'Payment Proof Screenshot') + '  •  PSCMRCET Official Verification';
                }
                ctx.fillText(line3, marginX, bannerHeight * 0.85);
              }

              // Draw the participant's original screenshot image
              ctx.drawImage(img, 0, bannerHeight, w, h);

              if (canvas.toBlob) {
                canvas.toBlob(function (blob) {
                  if (blob) {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = outName;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    setTimeout(() => URL.revokeObjectURL(url), 6000);
                    return;
                  }
                  fallbackDownloadDataUrl(canvas, outName);
                }, 'image/png');
              } else {
                fallbackDownloadDataUrl(canvas, outName);
              }
            } catch (e) {
              console.warn('Canvas conversion error, using direct download:', e);
              const a = document.createElement('a');
              a.href = src;
              a.download = outName;
              document.body.appendChild(a);
              a.click();
              document.body.removeChild(a);
            }
          };

          img.onerror = function () {
            const a = document.createElement('a');
            a.href = src;
            a.download = outName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
          };

          img.src = src;
        }

        function fallbackDownloadDataUrl(canvas, filename) {
          const pngDataUrl = canvas.toDataURL('image/png');
          const a = document.createElement('a');
          a.href = pngDataUrl;
          a.download = filename;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        }

        function isPscmrCollege(c) {
          if (!c) return false;
          const s = String(c).toLowerCase();
          return s.includes('pscmr') || s.includes('pscmrcet') || s.includes('potti sriramulu') || s.includes('chalavadi');
        }

        function openOfficialIdCardModal(teamId, name, college, branch, year, roll, ieee) {
          document.getElementById('cardTeamId').textContent = teamId;
          document.getElementById('cardBadgeId').textContent = teamId;
          document.getElementById('cardName').textContent = name;
          document.getElementById('cardCollege').textContent = college || 'PSCMR CET';
          document.getElementById('cardBranch').textContent = branch || 'N/A';
          document.getElementById('cardYear').textContent = year || 'N/A';

          const catEl = document.getElementById('cardCategory');
          if (catEl) {
            if (ieee && !String(ieee).toLowerCase().includes('non-ieee')) {
              catEl.textContent = '⚡ ' + String(ieee).toUpperCase();
              catEl.style.color = '#00f2fe';
              catEl.style.borderColor = '#00f2fe';
              catEl.style.background = 'rgba(0, 242, 254, 0.12)';
            } else {
              catEl.textContent = 'NON-IEEE DELEGATE';
              catEl.style.color = '#94a3b8';
              catEl.style.borderColor = '#475569';
              catEl.style.background = 'rgba(71, 85, 105, 0.2)';
            }
          }

          const isPscmr = isPscmrCollege(college);
          const rollBox = document.getElementById('cardRollBox');
          const grid = document.getElementById('cardBranchYearGrid');
          if (isPscmr && roll && roll !== 'N/A' && roll !== '-') {
            if (rollBox) {
              rollBox.style.display = 'block';
              document.getElementById('cardRoll').textContent = roll;
            }
            if (grid) {
              grid.style.gridTemplateColumns = '1.2fr 1fr 1fr';
            }
          } else {
            if (rollBox) {
              rollBox.style.display = 'none';
            }
            if (grid) {
              grid.style.gridTemplateColumns = '1fr 1fr';
            }
          }

          let domainBase = (localStorage.getItem('innowave_public_domain') || '').trim();
          if (!domainBase) {
            const pathDir = window.location.pathname.replace(/\/admin\.php$/, '').replace(/\/+$/, '');
            domainBase = `${window.location.protocol}//${window.location.host}${pathDir}`;
          }
          domainBase = domainBase.replace(/\/+$/, '');
          const verifyTargetUrl = `${domainBase}/verify-id.html?id=${encodeURIComponent(teamId)}`;
          const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(verifyTargetUrl)}`;
          document.getElementById('cardQrImg').src = qrUrl;

          document.getElementById('idCardModal').style.display = 'flex';
        }

        function closeIdCardModal() {
          document.getElementById('idCardModal').style.display = 'none';
        }

        function printIdCard() {
          const card = document.getElementById('printableIdCard');
          if (!card) {
            window.print();
            return;
          }

          let iframe = document.getElementById('idCardPrintFrame');
          if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'idCardPrintFrame';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.style.visibility = 'hidden';
            document.body.appendChild(iframe);
          }

          const cardClone = card.cloneNode(true);
          cardClone.style.margin = '0 auto';
          cardClone.style.width = '440px';
          cardClone.style.maxWidth = '440px';
          cardClone.style.boxShadow = 'none';

          const doc = iframe.contentWindow.document;
          doc.open();
          doc.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>InnoWave2k26_Official_Delegate_Pass</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    @page {
      size: A4 portrait;
      margin: 10mm;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    html, body {
      background: #ffffff !important;
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
      display: flex !important;
      justify-content: center !important;
      align-items: flex-start !important;
      font-family: 'Inter', sans-serif !important;
    }
    #printableIdCard {
      width: 440px !important;
      max-width: 440px !important;
      margin: 10px auto !important;
      background: #04091a !important;
      border: 2.5px solid #00f2fe !important;
      border-radius: 24px !important;
      padding: 20px 16px !important;
      color: #ffffff !important;
      box-sizing: border-box !important;
      page-break-inside: avoid !important;
      break-inside: avoid !important;
    }
  </style>
</head>
<body>
  ${cardClone.outerHTML}
</body>
</html>`);
        doc.close();

        const imgs = doc.querySelectorAll('img');
        let loadedCount = 0;
        const totalImgs = imgs.length;

        const triggerPrint = () => {
          setTimeout(() => {
            try {
              iframe.contentWindow.focus();
              iframe.contentWindow.print();
            } catch (e) {
              console.error(e);
              window.print();
            }
          }, 350);
        };

        if (totalImgs === 0) {
          triggerPrint();
        } else {
          imgs.forEach(img => {
            if (img.complete) {
              loadedCount++;
              if (loadedCount >= totalImgs) triggerPrint();
            } else {
              img.onload = img.onerror = () => {
                loadedCount++;
                if (loadedCount >= totalImgs) triggerPrint();
              };
            }
          });
          setTimeout(triggerPrint, 800);
        }
      }

      async function updateStatus(id, action) {
        if (action === 'delete' && !confirm('Are you sure you want to delete this registration record?')) return;
        try {
          const formData = new FormData();
          formData.append('id', id);
          formData.append('action', action);
          const res = await fetch('api/admin-action.php', { method: 'POST', body: formData });
          const data = await res.json().catch(() => null);
          if (data && data.ok) {
            window.location.reload();
            return;
          }
        } catch (err) {
          console.error(err);
        }
        // Fail-safe direct fallback
        window.location.href = `admin.php?do_action=${encodeURIComponent(action)}&id=${encodeURIComponent(id)}`;
      }

      let activeAmountEditId = null;

      function openAmountModal(id, teamId, name, currentAmt) {
        activeAmountEditId = id;
        const teamEl = document.getElementById('amountModalTeamId');
        const nameEl = document.getElementById('amountModalName');
        const inp = document.getElementById('amountModalInput');
        if (teamEl) teamEl.textContent = teamId || ('IW26-' + id);
        if (nameEl) nameEl.textContent = name || 'Participant';
        if (inp) inp.value = (currentAmt !== undefined && currentAmt !== null) ? currentAmt : 100;
        const modal = document.getElementById('amountModal');
        if (modal) modal.style.display = 'flex';
        setTimeout(() => {
          if (inp) { inp.focus(); inp.select(); }
        }, 80);
      }

      function closeAmountModal() {
        const modal = document.getElementById('amountModal');
        if (modal) modal.style.display = 'none';
        activeAmountEditId = null;
      }

      function setAmountPreset(amt) {
        const inp = document.getElementById('amountModalInput');
        if (inp) inp.value = amt;
      }

      async function saveParticipantAmount() {
        if (!activeAmountEditId) return;
        const inp = document.getElementById('amountModalInput');
        const amtVal = parseInt(inp ? inp.value : '0', 10);
        if (isNaN(amtVal) || amtVal < 0) {
          alert('Please enter a valid amount of ₹0 or greater.');
          return;
        }

        const saveBtn = document.getElementById('amountModalSaveBtn');
        if (saveBtn) {
          saveBtn.disabled = true;
          saveBtn.textContent = '⏳ Saving...';
        }

        try {
          const formData = new FormData();
          formData.append('id', activeAmountEditId);
          formData.append('action', 'update_amount');
          formData.append('amount', amtVal);

          const res = await fetch('api/admin-action.php', { method: 'POST', body: formData });
          const data = await res.json().catch(() => null);
          if (data && data.ok) {
            window.location.reload();
            return;
          } else if (data && data.error) {
            alert('Error: ' + data.error);
          }
        } catch (err) {
          console.error('API error:', err);
        }

        // Fail-safe direct fallback
        window.location.href = `admin.php?do_action=update_amount&id=${encodeURIComponent(activeAmountEditId)}&amount=${encodeURIComponent(amtVal)}`;
      }

      function savePublicDomain() {
        const val = (document.getElementById('publicDomainInput').value || '').trim();
        if (!val) {
          localStorage.removeItem('innowave_public_domain');
          alert('Public Domain cleared! Reverted to automatic server host detection.');
        } else {
          localStorage.setItem('innowave_public_domain', val);
          alert('Public Domain saved successfully!\n\nAll printed ID card QR codes will now use:\n' + val + '/verify-id.html?id=...');
        }
      }

      document.addEventListener('DOMContentLoaded', () => {
        const saved = localStorage.getItem('innowave_public_domain') || '';
        const inp = document.getElementById('publicDomainInput');
        if (inp) {
          inp.value = saved;
        }
      });
    </script>
    <?php endif; ?>
  </div>

  <!-- Floating Movable & Draggable Scroll To Top Button -->
  <button type="button" id="scrollTopBtn" aria-label="Scroll to Top"
    style="display:none; position:fixed; bottom:28px; right:28px; width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg, #00d4ff 0%, #0055ff 100%); color:#ffffff; border:1.5px solid rgba(255,255,255,0.45); font-size:20px; font-weight:900; cursor:grab; z-index:99999; box-shadow:0 8px 24px rgba(0,212,255,0.45); transition:transform 0.2s, box-shadow 0.2s; justify-content:center; align-items:center; outline:none; touch-action:none; user-select:none; -webkit-user-select:none;"
    onmouseenter="if(!window.__isDraggingScrollBtn){this.style.transform='scale(1.1)'; this.style.boxShadow='0 12px 30px rgba(0,212,255,0.65)';}"
    onmouseleave="if(!window.__isDraggingScrollBtn){this.style.transform='scale(1)'; this.style.boxShadow='0 8px 24px rgba(0,212,255,0.45)';}">
    ▲
  </button>

  <script>
    (function initMovableScrollBtn() {
      const btn = document.getElementById('scrollTopBtn');
      if (!btn) return;

      let isDragging = false;
      let hasMoved = false;
      let startX = 0, startY = 0;
      let initialLeft = 0, initialTop = 0;

      function getCoords(e) {
        if (e.touches && e.touches.length > 0) {
          return { x: e.touches[0].clientX, y: e.touches[0].clientY };
        }
        return { x: e.clientX, y: e.clientY };
      }

      function onStart(e) {
        isDragging = true;
        window.__isDraggingScrollBtn = true;
        hasMoved = false;
        const c = getCoords(e);
        startX = c.x;
        startY = c.y;

        const rect = btn.getBoundingClientRect();
        initialLeft = rect.left;
        initialTop = rect.top;

        btn.style.cursor = 'grabbing';
      }

      function onMove(e) {
        if (!isDragging) return;
        const c = getCoords(e);
        const dx = c.x - startX;
        const dy = c.y - startY;

        if (Math.abs(dx) > 4 || Math.abs(dy) > 4) {
          hasMoved = true;
        }

        if (hasMoved) {
          if (e.cancelable) e.preventDefault();

          let newLeft = initialLeft + dx;
          let newTop = initialTop + dy;

          const btnW = btn.offsetWidth || 48;
          const btnH = btn.offsetHeight || 48;
          const maxLeft = window.innerWidth - btnW - 8;
          const maxTop = window.innerHeight - btnH - 8;

          newLeft = Math.max(8, Math.min(newLeft, maxLeft));
          newTop = Math.max(8, Math.min(newTop, maxTop));

          btn.style.left = newLeft + 'px';
          btn.style.top = newTop + 'px';
          btn.style.right = 'auto';
          btn.style.bottom = 'auto';
        }
      }

      function onEnd(e) {
        if (!isDragging) return;
        isDragging = false;
        window.__isDraggingScrollBtn = false;
        btn.style.cursor = 'grab';

        if (!hasMoved) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      }

      btn.addEventListener('mousedown', onStart);
      window.addEventListener('mousemove', onMove, { passive: false });
      window.addEventListener('mouseup', onEnd);

      btn.addEventListener('touchstart', onStart, { passive: false });
      window.addEventListener('touchmove', onMove, { passive: false });
      window.addEventListener('touchend', onEnd);
      window.addEventListener('touchcancel', onEnd);

      btn.onclick = function (e) {
        e.preventDefault();
      };

      function updateVisibility() {
        if (window.scrollY > 160 || document.documentElement.scrollTop > 160) {
          btn.style.display = 'flex';
        } else {
          btn.style.display = 'none';
        }
      }

      window.addEventListener('scroll', updateVisibility);
      updateVisibility();
    })();
  </script>
</body>

</html>