<?php
/**
 * INNOWAVE-2K26 — Native PHP Admin Dashboard Engine
 */
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

// Direct Action Handler (Approve / Pending / Delete)
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
    }
    header('Location: admin.php');
    exit;
}

// Fetch Registrations Directly from Database
$rows = [];
if ($isLoggedIn && isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT * FROM registrations ORDER BY id DESC");
        $rows = $stmt ? $stmt->fetchAll() : [];
    } catch (Exception $e) {
        $rows = [];
    }
}

$totalRegs = count($rows);
$ieeeCount = 0;
$nonIeeeCount = 0;
$totalAmountCollected = 0;
$pendingCount = 0;

$utrCounts = [];
foreach ($rows as $r) {
    $ref = strtoupper(trim($r['payment_ref'] ?? ''));
    if (!empty($ref)) {
        $utrCounts[$ref] = ($utrCounts[$ref] ?? 0) + 1;
    }
}

foreach ($rows as $r) {
    if (($r['ieee_member'] ?? '') === 'Yes') {
        $ieeeCount++;
    } else {
        $nonIeeeCount++;
    }
    $st = strtolower(trim($r['payment_status'] ?? ''));
    $amt = intval($r['amount'] ?? 0);
    if ($amt <= 0) {
        $amt = (($r['ieee_member'] ?? '') === 'Yes') ? 100 : 200;
    }
    if ($st === 'paid' || $st === 'confirmed' || $st === 'approved' || strpos($st, 'paid') !== false) {
        $totalAmountCollected += $amt;
    } else {
        $pendingCount++;
    }
}

function esc($s) {
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
  * { margin:0; padding:0; box-sizing:border-box; }
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
  .wrap { max-width: 1320px; margin: 0 auto; }
  .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px dashed var(--border);
  }
  .title { font-family: 'Space Grotesk', sans-serif; font-size: 24px; font-weight: 800; color: var(--accent); }
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
  .stat-card .v { font-size: 28px; font-weight: 900; margin-top: 4px; }
  .table-wrap {
    background: var(--bg-panel);
    border: 1.5px solid var(--border);
    border-radius: 20px;
    overflow-x: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  }
  table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
  th, td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.08); vertical-align: middle; }
  th { background: rgba(0, 242, 254, 0.08); color: var(--accent); font-weight: 800; text-transform: uppercase; font-size: 11.5px; letter-spacing: 0.05em; }
  tr:hover { background: rgba(255,255,255,0.02); }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
  .badge-paid { background: rgba(0, 230, 118, 0.2); color: #00e676; border: 1px solid #00e676; }
  .badge-pending { background: rgba(251, 191, 36, 0.2); color: #fbbf24; border: 1px solid #fbbf24; }
  .btn { padding: 6px 12px; border-radius: 6px; border: none; font-size: 11.5px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; margin-right: 4px; margin-bottom: 4px; }
  .btn-approve { background: #00e676; color: #030712; }
  .btn-view { background: rgba(0, 242, 254, 0.2); color: #00f2fe; border: 1px solid #00f2fe; }
  .btn-card { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #030712; font-weight: 800; }
  .btn-delete { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
  .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
  .modal-content { background: var(--bg-primary); border: 2px solid var(--accent); border-radius: 20px; padding: 24px; max-width: 540px; width: 100%; text-align: center; max-height: 90vh; overflow-y: auto; }

  /* Laptop / Desktop Responsive Enhancements (>= 1024px) */
  @media (min-width: 1024px) {
    .header { flex-direction: row; align-items: center; justify-content: space-between; }
    .stats-grid { grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .table-wrap { overflow-x: auto; }
  }

  /* Mobile Responsive Enhancements (<= 768px) - According to Laptop View */
  @media (max-width: 768px) {
    body { padding: 12px 8px; }
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
    .header > div:last-child {
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
    .bank-pills-wrap > div {
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
    th, td {
      padding: 10px 10px !important;
    }

    .modal-content {
      max-width: 96% !important;
      padding: 16px !important;
      margin: 10px auto !important;
      max-height: 92vh !important;
    }
    #printableIdCard {
      max-width: 100% !important;
      width: 100% !important;
    }
  }

  @media (max-width: 480px) {
    .title { font-size: 16px !important; }
  }
  
  @media print {
    @page {
      size: auto;
      margin: 5mm;
    }
    html, body {
      background: #04091a !important;
      color: #ffffff !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    body * {
      visibility: hidden !important;
    }
    #printableIdCard, #printableIdCard * {
      visibility: visible !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    #printableIdCard {
      position: absolute !important;
      left: 50% !important;
      top: 10px !important;
      transform: translateX(-50%) !important;
      width: 440px !important;
      max-width: 100% !important;
      background: #04091a !important;
      border: 2.5px solid #00f2fe !important;
      box-shadow: none !important;
    }
    .modal-overlay, .modal-content {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    .modal-overlay button, #idCardModal h3, .id-card-btn-bar {
      display: none !important;
    }
  }
</style>
</head>
<body>
<div class="wrap">

<?php if (!$isLoggedIn): ?>
  <div style="max-width:400px; margin:80px auto; background:var(--bg-panel); border:1.5px solid var(--border); border-radius:24px; padding:32px 24px; text-align:center; box-shadow:0 15px 40px rgba(0,0,0,0.6);">
    <div style="font-size:42px; margin-bottom:12px;">🛡️</div>
    <h2 style="font-family:'Space Grotesk',sans-serif; color:var(--accent); font-size:22px; font-weight:800; margin-bottom:6px;">ORGANIZER LOGIN</h2>
    <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">Enter your admin passcode to access InnoWave-2k26 live registrations & print official delegate cards.</p>
    
    <?php if ($authError): ?>
      <div style="background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#ef4444; padding:10px; border-radius:8px; font-size:12.5px; margin-bottom:16px; font-weight:700;">
        ⚠️ <?= esc($authError) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="login">
      <input type="password" name="password" placeholder="Enter Admin Passcode" required style="width:100%; background:var(--bg-primary); border:1.5px solid var(--border); border-radius:10px; padding:12px; color:#ffffff; font-size:15px; outline:none; margin-bottom:16px; text-align:center;">
      <button type="submit" style="width:100%; background:linear-gradient(135deg, var(--accent), #00a8ff); color:#030712; font-weight:900; padding:12px; border-radius:10px; border:none; font-size:15px; cursor:pointer;">
        🔓 ACCESS ADMIN DASHBOARD
      </button>
    </form>
  </div>
<?php else: ?>

  <div class="header">
    <div>
      <div class="title">⚡ INNOWAVE-2K26 ORGANIZER ADMIN & ID CARD PRINTER</div>
      <div style="color:var(--text-muted); font-size:13px; margin-top:2px;">Live MySQL Registration Control & Official Delegate ID Card Printing Portal</div>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
      <a href="api/admin.php?action=export-php&password=innowave2k26" class="btn btn-card" style="text-decoration:none; padding:10px 16px; font-size:13px; display:inline-flex; align-items:center; gap:6px;">
        📊 📥 DOWNLOAD PHP ADMIN EXCEL (.CSV)
      </a>
      <a href="admin.php?action=logout" class="btn btn-delete" style="text-decoration:none; padding:10px 16px;">🔒 Logout</a>
    </div>
  </div>

  <!-- Official Bank Account Banner -->
  <div class="bank-banner-wrap" style="background:linear-gradient(135deg, rgba(0, 242, 254, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%); border:1.5px solid var(--accent); border-radius:16px; padding:16px 20px; margin-bottom:24px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
    <div>
      <div style="font-size:11px; font-weight:800; color:var(--accent); text-transform:uppercase; letter-spacing:0.08em;">OFFICIAL BENEFICIARY ACCOUNT</div>
      <div style="font-family:'Space Grotesk',sans-serif; font-size:15px; font-weight:800; color:#ffffff; margin-top:2px;">POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE</div>
    </div>
    <div class="bank-pills-wrap" style="display:flex; gap:16px;">
      <div style="background:rgba(255,255,255,0.06); padding:8px 14px; border-radius:10px; border:1px solid rgba(255,255,255,0.15);">
        <div style="color:#94a3b8; font-size:10px; font-weight:700;">ACCOUNT NO</div>
        <div style="color:#ffffff; font-size:15px; font-weight:900; font-family:monospace;">1414155000131347</div>
      </div>
      <div style="background:rgba(255,255,255,0.06); padding:8px 14px; border-radius:10px; border:1px solid rgba(255,255,255,0.15);">
        <div style="color:#94a3b8; font-size:10px; font-weight:700;">IFSC CODE</div>
        <div style="color:#00f2fe; font-size:15px; font-weight:900; font-family:monospace;">KVBL0001414</div>
      </div>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div style="color:#94a3b8; font-size:12px; font-weight:700;">TOTAL PARTICIPANTS</div>
      <div class="v"><?= $totalRegs ?></div>
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
      <div style="font-size:11px; color:#94a3b8; margin-top:4px;">From <?= ($totalRegs - $pendingCount) ?> Approved Participants</div>
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
              $hasIeeeCard = !empty($r['ieee_card']);
              $hasProof = !empty($r['payment_screenshot']) || !empty($r['payment_proof']);
              $proofImg = !empty($r['payment_screenshot']) ? $r['payment_screenshot'] : ($r['payment_proof'] ?? '');
              $teamIdEsc = esc($r['team_id'] ?? ('IW26-' . $r['id']));
              $nameEsc = esc($r['leader_name'] ?? 'Participant');
              $collegeEsc = esc($r['college_name'] ?? 'PSCMR CET');
              $branchEsc = esc($r['branch'] ?? '');
              $yearEsc = esc($r['year'] ?? '');
              $rollEsc = esc($r['roll_no'] ?? 'N/A');
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
                <div style="font-weight:900; font-size:13px; margin-top:4px; color:#00e676;">₹<?= esc($r['amount'] ?? 100) ?></div>
              </td>
              <td>
                <strong><?= $nameEsc ?></strong><br>
                <a href="mailto:<?= esc($r['leader_email']) ?>" style="color:#00f2fe; text-decoration:none; font-size:12px;"><?= esc($r['leader_email']) ?></a><br>
                <span style="color:#94a3b8; font-size:12px;">📱 <?= esc($r['leader_phone']) ?></span>
              </td>
              <td>
                <strong><?= $collegeEsc ?></strong>
                <?php if ($isPscmr && !empty($r['roll_no']) && $rollEsc !== 'N/A' && $rollEsc !== '-'): ?>
                  <br><span style="font-size:11px; font-weight:800; color:#00e676; background:rgba(0,230,118,0.12); border:1px solid rgba(0,230,118,0.3); padding:2px 7px; border-radius:5px; display:inline-block; margin-top:2px;">🎓 Adm No: <?= $rollEsc ?></span>
                <?php endif; ?>
                <br><span style="color:#94a3b8; font-size:12px;"><?= $branchEsc ?> (<?= $yearEsc ?>)</span>
              </td>
              <td>
                <?= ($r['ieee_member'] === 'Yes') ? '<span style="color:#00f2fe; font-weight:800;">✓ IEEE Member</span>' : '<span style="color:#94a3b8;">Non-IEEE</span>' ?>
                <?php if (!empty($r['ieee_id'])): ?>
                  <br><span style="font-size:11px; color:#94a3b8;">ID: <?= esc($r['ieee_id']) ?></span>
                <?php endif; ?>
                <?php if ($hasIeeeCard): ?>
                  <br><button class="btn btn-view" style="margin-top:4px; font-size:10px; padding:3px 6px;" onclick="viewImage('🪪 IEEE Membership Card Proof', '<?= esc($r['ieee_card']) ?>')">🪪 View Card</button>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $cleanRefUpper = strtoupper(trim($utr));
                  $isClonedUtr = (!empty($cleanRefUpper) && isset($utrCounts[$cleanRefUpper]) && $utrCounts[$cleanRefUpper] > 1);
                ?>
                <?php if (!empty($utr)): ?>
                  <div style="font-family:monospace; color:#00e676; font-weight:800; font-size:13px;">UTR: <?= esc($utr) ?></div>
                  <?php if ($isClonedUtr): ?>
                    <div style="color:#ef4444; font-weight:900; font-size:11px; margin-top:2px; background:rgba(239,68,68,0.15); border:1px solid #ef4444; padding:2px 6px; border-radius:4px; display:inline-block;">
                      🚨 CLONED UTR (<?= $utrCounts[$cleanRefUpper] ?> Duplicate Submissions)
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:#94a3b8; font-size:12px;">No UTR submitted</span>
                <?php endif; ?>
                <?php if ($hasProof): ?>
                  <br><button class="btn btn-view" style="margin-top:4px; font-size:10px; padding:3px 6px;" onclick="viewImage('🖼️ Payment Screenshot Proof', '<?= esc($proofImg) ?>')">🖼️ View Screenshot</button>
                <?php endif; ?>
              </td>
              <td>
                <button class="btn btn-card" onclick="openOfficialIdCardModal('<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '<?= $branchEsc ?>', '<?= $yearEsc ?>', '<?= $rollEsc ?>', '<?= $ieeeEsc ?>')">🪪 PRINT ID CARD</button><br>
                <?php if (!$isPaid): ?>
                  <button class="btn btn-approve" onclick="updateStatus(<?= $r['id'] ?>, 'approve_paid')">✓ Approve</button>
                <?php else: ?>
                  <button class="btn btn-view" onclick="updateStatus(<?= $r['id'] ?>, 'mark_pending')">⏳ Pending</button>
                <?php endif; ?>
                <button class="btn btn-delete" onclick="updateStatus(<?= $r['id'] ?>, 'delete')">✕</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Image Viewer Modal -->
  <div class="modal-overlay" id="imgModal" onclick="closeImgModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
      <h3 id="imgModalTitle" style="color:var(--accent); font-size:18px; margin-bottom:14px;">IMAGE PROOF</h3>
      <img id="imgModalSrc" src="" style="max-width:100%; max-height:70vh; border-radius:12px; border:2px solid var(--accent); display:block; margin:0 auto 16px; object-fit:contain;">
      <button class="btn btn-view" style="width:100%; padding:10px; font-size:14px;" onclick="closeImgModal()">Close Viewer</button>
    </div>
  </div>

  <!-- Admin Official ID Card Printer Modal -->
  <div class="modal-overlay" id="idCardModal" onclick="closeIdCardModal()">
    <div class="modal-content" onclick="event.stopPropagation()" style="max-width:480px; background:#020612; border:2px solid var(--accent); border-radius:24px; padding:20px; text-align:center;">
      <h3 style="color:var(--gold); font-size:16px; margin-bottom:12px; font-family:'Space Grotesk',sans-serif;">🪪 OFFICIAL PARTICIPANT ID CARD PRINTER</h3>
      
      <!-- Printable Card Container (Exact 1:1 Graphic Card Layout) -->
      <div id="printableIdCard" style="width:100%; max-width:440px; margin:0 auto; background:#04091a; border:2.5px solid #00f2fe; border-radius:24px; padding:20px 16px; color:#ffffff; font-family:'Inter',sans-serif; box-sizing:border-box; position:relative; box-shadow:0 0 35px rgba(0,242,254,0.3); text-align:center;">
        
        <!-- TOP DARK NAVY BANNER HEADER -->
        <div style="text-align:center; margin-bottom:12px;">
          <!-- Gold Pill Badge -->
          <div style="display:inline-block; background:rgba(251, 191, 36, 0.1); border:1.5px solid #fbbf24; color:#fbbf24; border-radius:20px; padding:4px 16px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">
            👑 OFFICIAL DELEGATE PASS
          </div>
          
          <!-- Title -->
          <div style="font-family:'Space Grotesk',sans-serif; font-size:26px; font-weight:900; color:#ffffff; letter-spacing:0.05em; line-height:1.1; margin-bottom:4px;">
            INNOWAVE-2K26
          </div>

          <!-- Subtitle -->
          <div style="font-size:11px; font-weight:800; color:#fbbf24; letter-spacing:0.08em; text-transform:uppercase;">
            ENGINEER'S DAY CELEBRATION
          </div>
          <div style="font-size:10px; font-weight:800; color:#38bdf8; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:10px;">
            NATIONAL LEVEL FEST
          </div>

          <!-- Participant ID Cyan Pill Badge -->
          <div style="display:inline-flex; align-items:center; justify-content:center; gap:6px; background:rgba(0, 242, 254, 0.1); border:1.5px solid #00f2fe; color:#00f2fe; border-radius:20px; padding:5px 18px; font-size:12px; font-weight:800; font-family:'Space Grotesk',monospace;">
            <span style="background:#00f2fe; color:#04091a; padding:1px 4px; border-radius:4px; font-size:10px; font-weight:900;">ID</span>
            <span>PARTICIPANT ID: <span id="cardTeamId">IW26-0001</span></span>
          </div>
        </div>

        <!-- AMBER ACCENT DIVIDER LINE -->
        <div style="height:3px; background:linear-gradient(90deg, transparent, #fbbf24, transparent); margin-bottom:14px; border-radius:2px;"></div>

        <!-- MIDDLE BODY CONTAINER (White background, cyan border) -->
        <div style="background:#ffffff; border:2px solid #00f2fe; border-radius:16px; padding:12px; color:#0f172a; margin-bottom:14px; text-align:left;">
          
          <!-- 1. Registered Participant Name Box (Cream Yellow) -->
          <div style="background:#fffbeb; border:1.5px solid #fef08a; border-radius:12px; padding:10px 14px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
            <div>
              <div style="color:#92400e; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                <span>👤</span> REGISTERED PARTICIPANT NAME
              </div>
              <div id="cardName" style="font-family:'Space Grotesk',sans-serif; font-size:20px; font-weight:900; color:#0f172a;">
                jai
              </div>
            </div>
            <div style="background:#041b2d; color:#00f2fe; border:1px solid #00f2fe; font-size:11px; font-weight:800; padding:4px 10px; border-radius:8px; font-family:monospace;">
              ID: <span id="cardBadgeId">IW26-0001</span>
            </div>
          </div>

          <!-- 2. College / Institution Name Box -->
          <div style="background:#ffffff; border:1px solid #e2e8f0; border-left:4px solid #0284c7; border-radius:10px; padding:8px 12px; margin-bottom:10px;">
            <div style="color:#0284c7; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
              <span>🏫</span> COLLEGE / INSTITUTION NAME
            </div>
            <div id="cardCollege" style="font-size:14px; font-weight:800; color:#0f172a;">
              pscmrcet
            </div>
          </div>

          <!-- 3. Branch & Year of Study (with Admission No for PSCMR only) -->
          <div id="cardBranchYearGrid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
            <div id="cardRollBox" style="display:none; background:#ffffff; border:1px solid #a7f3d0; border-left:4px solid #059669; border-radius:10px; padding:8px 12px; text-align:left;">
              <div style="color:#059669; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                <span>🎫</span> ADMISSION NO
              </div>
              <div id="cardRoll" style="font-size:14px; font-weight:800; color:#064e3b;">
                -
              </div>
            </div>
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-left:4px solid #0284c7; border-radius:10px; padding:8px 12px; text-align:left;">
              <div style="color:#0284c7; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                <span>🎓</span> BRANCH
              </div>
              <div id="cardBranch" style="font-size:14px; font-weight:800; color:#0f172a;">
                CSO
              </div>
            </div>
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-left:4px solid #0284c7; border-radius:10px; padding:8px 12px; text-align:left;">
              <div style="color:#0284c7; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:2px;">
                <span>📅</span> YEAR OF STUDY
              </div>
              <div id="cardYear" style="font-size:14px; font-weight:800; color:#0f172a;">
                4th Year
              </div>
            </div>
          </div>

          <!-- 4. Events Evaluation Checklist Table -->
          <div style="border:1px solid #cbd5e1; border-radius:12px; overflow:hidden;">
            <div style="background:#04091a; color:#ffffff; padding:8px 12px; display:flex; justify-content:space-between; align-items:center; font-size:11px; font-weight:800;">
              <div style="display:flex; align-items:center; gap:6px;">
                <span>📋</span> EVENTS EVALUATION CHECKLIST
              </div>
              <div style="color:#00f2fe; font-family:'Space Grotesk',sans-serif;">
                INNOWAVE-2K26
              </div>
            </div>
            
            <table style="width:100%; border-collapse:collapse; font-size:11px; background:#ffffff;">
              <thead>
                <tr style="border-bottom:1px solid #e2e8f0; color:#475569; font-size:9.5px; font-weight:800; text-transform:uppercase;">
                  <th style="padding:6px 10px; text-align:left;">BROCHURE EVENT NAME</th>
                  <th style="padding:6px 10px; text-align:center; width:70px;">MARK [✓]</th>
                  <th style="padding:6px 10px; text-align:right;">EVENT HEAD SIGN</th>
                </tr>
              </thead>
              <tbody style="color:#0f172a; font-weight:700;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:6px 10px;">🧠 Technical Quiz</td>
                  <td style="padding:6px 10px; text-align:center;">
                    <div style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;"></div>
                  </td>
                  <td style="padding:6px 10px; text-align:right; color:#cbd5e1;">___________________</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:6px 10px;">💻 Coding Challenge</td>
                  <td style="padding:6px 10px; text-align:center;">
                    <div style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;"></div>
                  </td>
                  <td style="padding:6px 10px; text-align:right; color:#cbd5e1;">___________________</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:6px 10px;">🗺️ Tech Treasure Hunt</td>
                  <td style="padding:6px 10px; text-align:center;">
                    <div style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;"></div>
                  </td>
                  <td style="padding:6px 10px; text-align:right; color:#cbd5e1;">___________________</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:6px 10px;">🚀 Project Expo</td>
                  <td style="padding:6px 10px; text-align:center;">
                    <div style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;"></div>
                  </td>
                  <td style="padding:6px 10px; text-align:right; color:#cbd5e1;">___________________</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                  <td style="padding:6px 10px;">🤖 Prompt Engineering</td>
                  <td style="padding:6px 10px; text-align:center;">
                    <div style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;"></div>
                  </td>
                  <td style="padding:6px 10px; text-align:right; color:#cbd5e1;">___________________</td>
                </tr>
                <tr>
                  <td style="padding:6px 10px;">🎬 Reels (1 Min)</td>
                  <td style="padding:6px 10px; text-align:center;">
                    <div style="width:16px; height:16px; border:1.5px solid #94a3b8; border-radius:3px; margin:0 auto;"></div>
                  </td>
                  <td style="padding:6px 10px; text-align:right; color:#cbd5e1;">___________________</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

        <!-- BOTTOM DARK NAVY FOOTER SECTION -->
        <div style="display:flex; justify-content:space-between; align-items:flex-end; padding:4px 6px;">
          <!-- Left: QR Code & Verify Text -->
          <div style="display:flex; align-items:center; gap:10px;">
            <div style="background:#ffffff; padding:4px; border-radius:8px; border:1.5px solid #00f2fe; width:64px; height:64px; display:flex; align-items:center; justify-content:center;">
              <img id="cardQrImg" src="" alt="QR Code" style="width:100%; height:100%; object-fit:contain;">
            </div>
            <div style="text-align:left;">
              <div style="color:#00f2fe; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">
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
            <div style="color:#64748b; font-size:11px; margin-bottom:2px;">
              ___________________
            </div>
            <div style="color:#fbbf24; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">
              REGISTRATION SIGNATURE
            </div>
          </div>
        </div>

      </div>

      <div style="display:flex; gap:10px; margin-top:16px;">
        <button class="btn btn-approve" style="flex:1; padding:12px; font-size:14px;" onclick="printIdCard()">🖨️ PRINT THIS ID CARD</button>
        <button class="btn btn-view" style="padding:12px;" onclick="closeIdCardModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    function viewImage(title, src) {
      document.getElementById('imgModalTitle').textContent = title;
      document.getElementById('imgModalSrc').src = src;
      document.getElementById('imgModal').style.display = 'flex';
    }
    function closeImgModal() {
      document.getElementById('imgModal').style.display = 'none';
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
      window.print();
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

    btn.onclick = function(e) {
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