<?php
/**
 * INNOWAVE-2K26 — Native PHP Admin Dashboard Engine
 */
require_once __DIR__ . '/api/db.php';

$ADMIN_PASSWORD = 'innowave2k26';
session_start();

$authError = '';
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $pw = $_POST['password'] ?? '';
    if ($pw === $ADMIN_PASSWORD) {
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

foreach ($rows as $r) {
    if (($r['ieee_member'] ?? '') === 'Yes') {
        $ieeeCount++;
    } else {
        $nonIeeeCount++;
    }
    $st = $r['payment_status'] ?? '';
    if ($st === 'Paid' || $st === 'Confirmed') {
        $totalAmountCollected += intval($r['amount'] ?? 0);
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
    font-family: 'Inter', sans-serif;
    background: #030712;
    color: #ffffff;
    min-height: 100vh;
  }
  .header-banner {
    width:100%; text-align:center; background:#ffffff; border-bottom:2px solid var(--accent);
  }
  .header-banner img {
    width:100%; height:auto; display:block;
  }
  .login-box {
    max-width: 400px; margin: 100px auto; background: #0a192f; border: 2px solid #00f2fe;
    border-radius: 20px; padding: 35px 28px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.5);
  }
  .login-box h1 { font-family:'Space Grotesk', sans-serif; font-size:28px; margin-bottom:8px; color:#fff; }
  .login-box input {
    width:100%; padding:14px; margin:16px 0; border-radius:10px; border:1px solid #00f2fe;
    background:#031527; color:#fff; font-size:15px; outline:none; text-align:center;
  }
  .login-box button {
    width:100%; padding:14px; background:linear-gradient(135deg, #00f2fe 0%, #0066ff 100%);
    color:#030712; font-weight:800; font-size:15px; border:none; border-radius:10px; cursor:pointer;
  }
  .container { max-width: 1400px; margin: 0 auto; padding: 24px 20px; }
  .nav-bar {
    display:flex; justify-content:space-between; align-items:center; background:#081a2e;
    padding:16px 24px; border-bottom:1px solid #00f2fe; border-radius:16px; margin-bottom:24px;
  }
  .stats-grid {
    display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:18px; margin-bottom:24px;
  }
  .stat-card {
    background: #091c30; border:1.5px solid rgba(0,242,254,0.3); border-radius:16px; padding:20px; text-align:center;
  }
  .stat-card .v { font-size: 28px; font-weight: 900; color: #00f2fe; margin-top:4px; }
  .table-wrap { overflow-x: auto; background: #081a2e; border-radius:16px; border:1px solid #00f2fe; }
  table { width:100%; border-collapse:collapse; text-align:left; font-size:13.5px; }
  th { background:#031527; color:#00f2fe; padding:14px 12px; border-bottom:2px solid #00f2fe; text-transform:uppercase; font-size:11px; letter-spacing:1px; }
  td { padding:14px 12px; border-bottom:1px solid rgba(255,255,255,0.08); vertical-align:middle; }
  tr:hover { background: rgba(0,242,254,0.04); }
  .badge { display:inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:800; }
  .badge-paid { background:rgba(0,230,118,0.2); border:1px solid #00e676; color:#00e676; }
  .badge-pending { background:rgba(255,183,3,0.2); border:1px solid #ffb703; color:#ffb703; }
  .btn-action { padding:6px 12px; border-radius:8px; font-size:11.5px; font-weight:700; border:none; cursor:pointer; text-decoration:none; display:inline-block; }
  .btn-verify { background:#00e676; color:#030712; }
</style>
</head>
<body>

<header class="header-banner">
  <img src="assets/header_pscmr.jpeg" alt="PSCMRCET Header Banner">
</header>

<?php if (!$isLoggedIn): ?>
  <div class="login-box">
    <h1>InnoWave<span style="color:#00f2fe">2k26</span></h1>
    <p style="color:#94a3b8; font-size:13px">Organizer Admin Dashboard</p>
    <?php if ($authError): ?>
      <div style="color:#ff4d4d; font-size:13px; margin-top:10px"><?= esc($authError) ?></div>
    <?php endif; ?>
    <form method="POST" action="admin.php">
      <input type="hidden" name="action" value="login">
      <input type="password" name="password" placeholder="Enter Admin Password" required autofocus>
      <button type="submit">Sign In to Dashboard</button>
    </form>
  </div>
<?php else: ?>

  <div class="container">
    <div class="nav-bar">
      <div style="font-size:20px; font-weight:900; font-family:'Space Grotesk',sans-serif;">
        InnoWave<span style="color:#00f2fe">2k26</span> · PHP Admin Dashboard
      </div>
      <div>
        <a href="admin.php" class="btn-action" style="background:#00f2fe; color:#030712; margin-right:10px;">↻ Refresh Table</a>
        <a href="api/admin.php?action=export&password=innowave2k26" class="btn-action" style="background:#00e676; color:#030712; margin-right:10px;">⬇ Export Excel</a>
        <a href="admin.php?action=logout" class="btn-action" style="background:#ef4444; color:#fff;">Logout</a>
      </div>
    </div>

    <!-- Official College Bank Account Details Summary Card -->
    <div style="background:linear-gradient(135deg, rgba(3,24,46,0.95) 0%, rgba(10,37,64,0.95) 100%); border:2px solid #00f2fe; border-radius:18px; padding:18px 24px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
      <div style="display:flex; align-items:center; gap:14px;">
        <div style="background:rgba(0,242,254,0.15); border:1.5px solid #00f2fe; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px;">🏦</div>
        <div>
          <div style="color:#00f2fe; font-size:11px; font-weight:800; letter-spacing:1.5px;">OFFICIAL COLLEGE BANK ACCOUNT DETAILS</div>
          <div style="color:#ffffff; font-size:16px; font-weight:900; margin-top:2px;">POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE</div>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
        <div style="background:rgba(255,255,255,0.06); padding:8px 14px; border-radius:10px; border:1px solid rgba(255,255,255,0.15);">
          <div style="color:#94a3b8; font-size:10px; font-weight:700;">ACCOUNT NUMBER</div>
          <div style="color:#00e676; font-size:15px; font-weight:900; font-family:monospace;">1414155000131347</div>
        </div>
        <div style="background:rgba(255,255,255,0.06); padding:8px 14px; border-radius:10px; border:1px solid rgba(255,255,255,0.15);">
          <div style="color:#94a3b8; font-size:10px; font-weight:700;">IFSC CODE</div>
          <div style="color:#00f2fe; font-size:15px; font-weight:900; font-family:monospace;">KVBL0001414</div>
        </div>
        <div style="background:rgba(255,255,255,0.06); padding:8px 14px; border-radius:10px; border:1px solid rgba(255,255,255,0.15);">
          <div style="color:#94a3b8; font-size:10px; font-weight:700;">BANK NAME</div>
          <div style="color:#fbbf24; font-size:14px; font-weight:900;">Karur Vysya Bank (KVB)</div>
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
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Participant ID</th>
            <th>Payment Status</th>
            <th>Participant Name</th>
            <th>Contact Info</th>
            <th>College & Branch</th>
            <th>IEEE Member</th>
            <th>Submitted UTR / Proof</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:40px; color:#94a3b8;">
                🚫 No registrations found in database yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $st = $r['payment_status'] ?? 'Pending Payment Confirmation';
                $isPaid = ($st === 'Paid' || $st === 'Confirmed');
                $utr = $r['payment_ref'] ?? '';
              ?>
              <tr>
                <td><strong style="color:#00f2fe"><?= esc($r['team_id'] ?? ('IW26-' . $r['id'])) ?></strong></td>
                <td>
                  <?php if ($isPaid): ?>
                    <span class="badge badge-paid">🟢 PAID & VERIFIED</span>
                  <?php else: ?>
                    <span class="badge badge-pending">⏳ PENDING VERIFICATION</span>
                  <?php endif; ?>
                  <div style="font-weight:800; font-size:12px; margin-top:4px;">₹<?= esc($r['amount'] ?? 100) ?></div>
                </td>
                <td><strong><?= esc($r['leader_name'] ?? 'Participant') ?></strong></td>
                <td>
                  <a href="mailto:<?= esc($r['leader_email']) ?>" style="color:#00f2fe; text-decoration:none;"><?= esc($r['leader_email']) ?></a><br>
                  <span style="color:#94a3b8; font-size:12px;"><?= esc($r['leader_phone']) ?></span>
                </td>
                <td>
                  <strong><?= esc($r['college_name'] ?? 'PSCMR CET') ?></strong><br>
                  <span style="color:#94a3b8; font-size:12px;"><?= esc($r['branch'] ?? '') ?> (<?= esc($r['year'] ?? '') ?>)</span>
                </td>
                <td>
                  <?= ($r['ieee_member'] === 'Yes') ? '<span style="color:#00f2fe; font-weight:800;">✓ IEEE</span>' : '<span style="color:#94a3b8;">Non-IEEE</span>' ?>
                  <?php if (!empty($r['ieee_id'])): ?>
                    <br><span style="font-size:11px; color:#94a3b8;">ID: <?= esc($r['ieee_id']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($utr)): ?>
                    <div style="font-family:monospace; color:#00e676; font-weight:800; font-size:13px;">UTR: <?= esc($utr) ?></div>
                  <?php else: ?>
                    <span style="color:#94a3b8; font-size:12px;">No UTR submitted yet</span>
                  <?php endif; ?>
                </td>
                <td style="color:#94a3b8; font-size:12px; white-space:nowrap;"><?= esc($r['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

</body>
</html>
