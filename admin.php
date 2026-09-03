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
  $st = $r['payment_status'] ?? '';
  if ($st === 'Paid' || $st === 'Confirmed') {
    $totalAmountCollected += intval($r['amount'] ?? 0);
  } else {
    $pendingCount++;
  }
}

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

    @media print {
      body * {
        visibility: hidden;
      }

      #printableIdCard,
      #printableIdCard * {
        visibility: visible;
      }

      #printableIdCard {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
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
          <div style="color:var(--text-muted); font-size:13px; margin-top:2px;">Live MySQL Registration Control & Official
            Delegate ID Card Printing Portal</div>
        </div>
        <div>
          <a href="admin.php?action=logout" class="btn btn-delete" style="text-decoration:none; padding:8px 16px;">🔒
            Logout</a>
        </div>
      </div>

      <!-- Official Bank Account Banner -->
      <div
        style="background:linear-gradient(135deg, rgba(0, 242, 254, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%); border:1.5px solid var(--accent); border-radius:16px; padding:16px 20px; margin-bottom:24px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
        <div>
          <div
            style="font-size:11px; font-weight:800; color:var(--accent); text-transform:uppercase; letter-spacing:0.08em;">
            OFFICIAL BENEFICIARY ACCOUNT</div>
          <div
            style="font-family:'Space Grotesk',sans-serif; font-size:15px; font-weight:800; color:#ffffff; margin-top:2px;">
            POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE</div>
        </div>
        <div style="display:flex; gap:16px;">
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
                ?>
                <tr id="row-<?= $r['id'] ?>">
                  <td><strong style="color:#00f2fe"><?= $teamIdEsc ?></strong></td>
                  <td>
                    <?php if ($isPaid): ?>
                      <span class="badge badge-paid">🟢 PAID & VERIFIED</span>
                    <?php else: ?>
                      <span class="badge badge-pending">⏳ PENDING</span>
                    <?php endif; ?>
                    <div style="font-weight:900; font-size:13px; margin-top:4px; color:#00e676;">
                      ₹<?= esc($r['amount'] ?? 100) ?></div>
                  </td>
                  <td>
                    <strong><?= $nameEsc ?></strong><br>
                    <a href="mailto:<?= esc($r['leader_email']) ?>"
                      style="color:#00f2fe; text-decoration:none; font-size:12px;"><?= esc($r['leader_email']) ?></a><br>
                    <span style="color:#94a3b8; font-size:12px;">📱 <?= esc($r['leader_phone']) ?></span>
                  </td>
                  <td>
                    <strong><?= $collegeEsc ?></strong><br>
                    <span style="color:#94a3b8; font-size:12px;"><?= $branchEsc ?> (<?= $yearEsc ?>)</span>
                  </td>
                  <td>
                    <?= ($r['ieee_member'] === 'Yes') ? '<span style="color:#00f2fe; font-weight:800;">✓ IEEE Member</span>' : '<span style="color:#94a3b8;">Non-IEEE</span>' ?>
                    <?php if (!empty($r['ieee_id'])): ?>
                      <br><span style="font-size:11px; color:#94a3b8;">ID: <?= esc($r['ieee_id']) ?></span>
                    <?php endif; ?>
                    <?php if ($hasIeeeCard): ?>
                      <br><button class="btn btn-view" style="margin-top:4px; font-size:10px; padding:3px 6px;"
                        onclick="viewImage('🪪 IEEE Membership Card Proof', '<?= esc($r['ieee_card']) ?>')">🪪 View
                        Card</button>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                    $cleanRefUpper = strtoupper(trim($utr));
                    $isClonedUtr = (!empty($cleanRefUpper) && isset($utrCounts[$cleanRefUpper]) && $utrCounts[$cleanRefUpper] > 1);
                    ?>
                    <?php if (!empty($utr)): ?>
                      <div style="font-family:monospace; color:#00e676; font-weight:800; font-size:13px;">UTR: <?= esc($utr) ?>
                      </div>
                      <?php if ($isClonedUtr): ?>
                        <div
                          style="color:#ef4444; font-weight:900; font-size:11px; margin-top:2px; background:rgba(239,68,68,0.15); border:1px solid #ef4444; padding:2px 6px; border-radius:4px; display:inline-block;">
                          🚨 CLONED UTR (<?= $utrCounts[$cleanRefUpper] ?> Duplicate Submissions)
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span style="color:#94a3b8; font-size:12px;">No UTR submitted</span>
                    <?php endif; ?>
                    <?php if ($hasProof): ?>
                      <br><button class="btn btn-view" style="margin-top:4px; font-size:10px; padding:3px 6px;"
                        onclick="viewImage('🖼️ Payment Screenshot Proof', '<?= esc($proofImg) ?>')">🖼️ View
                        Screenshot</button>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn btn-card"
                      onclick="openOfficialIdCardModal('<?= $teamIdEsc ?>', '<?= $nameEsc ?>', '<?= $collegeEsc ?>', '<?= $branchEsc ?>', '<?= $yearEsc ?>', '<?= $rollEsc ?>', '<?= $ieeeEsc ?>')">🪪
                      PRINT ID CARD</button><br>
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
          <img id="imgModalSrc" src=""
            style="max-width:100%; max-height:70vh; border-radius:12px; border:2px solid var(--accent); display:block; margin:0 auto 16px; object-fit:contain;">
          <button class="btn btn-view" style="width:100%; padding:10px; font-size:14px;" onclick="closeImgModal()">Close
            Viewer</button>
        </div>
      </div>

      <!-- Admin Official ID Card Printer Modal -->
      <div class="modal-overlay" id="idCardModal" onclick="closeIdCardModal()">
        <div class="modal-content" onclick="event.stopPropagation()"
          style="max-width:480px; background:#040914; border:2px solid var(--accent); border-radius:24px; padding:20px; text-align:center;">
          <h3 style="color:var(--gold); font-size:18px; margin-bottom:14px; font-family:'Space Grotesk',sans-serif;">🪪
            OFFICIAL PARTICIPANT ID CARD PRINTER</h3>

          <!-- Printable Card Container -->
          <div id="printableIdCard" style="width: 420px; margin: 0 auto; background: #ffffff; border: 2.5px solid #00c3ff; border-radius: 20px; overflow: hidden; font-family: 'Inter', sans-serif; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: left; color: #0f172a; position: relative;">
            
            <!-- 1. Top Dark Navy Blue Banner Header -->
            <div style="background: #020d1a; padding: 24px 20px 20px; text-align: center; border-bottom: 3px solid #e59a18; position: relative;">
              <!-- Gold Pill Badge -->
              <div style="display: inline-block; border: 1.5px solid #e59a18; border-radius: 20px; padding: 4px 16px; color: #e59a18; font-weight: 800; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 12px;">
                👑 OFFICIAL DELEGATE PASS
              </div>
              
              <!-- Main Event Title -->
              <div style="font-family: 'Space Grotesk', sans-serif; font-size: 28px; font-weight: 900; color: #ffffff; letter-spacing: 0.05em; line-height: 1.1; margin-bottom: 6px;">
                INNOWAVE-2K26
              </div>
              
              <!-- Subtitle -->
              <div style="color: #f59e0b; font-weight: 800; font-size: 13px; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 2px;">
                ENGINEER'S DAY CELEBRATION
              </div>
              <div style="color: #ffffff; font-weight: 700; font-size: 11px; letter-spacing: 0.15em; text-transform: uppercase; margin-bottom: 16px;">
                NATIONAL LEVEL FEST
              </div>

              <!-- Cyan Participant ID Pill -->
              <div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1.5px solid #00f2fe; border-radius: 20px; padding: 6px 18px; background: rgba(0, 242, 254, 0.05);">
                <span style="background: #7c4dff; color: #ffffff; font-weight: 900; font-size: 10px; padding: 2px 6px; border-radius: 4px;">ID</span>
                <span style="color: #00f2fe; font-family: monospace; font-weight: 800; font-size: 14px; letter-spacing: 0.05em;">PARTICIPANT ID: <span id="cardHeaderTeamId">IW26-0001</span></span>
              </div>
            </div>

            <!-- 2. Middle Body Section (White Container) -->
            <div style="padding: 16px 18px; background: #ffffff;">
              
              <!-- Registered Participant Name Box (Cream Yellow with Gold Border) -->
              <div style="background: #fffde7; border: 1.5px solid #fbc02d; border-radius: 14px; padding: 12px 14px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <div style="color: #9a6700; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 4px;">
                    <span>👤</span> REGISTERED PARTICIPANT NAME
                  </div>
                  <div id="cardName" style="font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                    jai
                  </div>
                </div>
                <div id="cardNameIdBadge" style="background: #004d61; color: #00f2fe; font-family: monospace; font-size: 12px; font-weight: 800; padding: 6px 12px; border-radius: 8px;">
                  ID: IW26-0001
                </div>
              </div>

              <!-- College / Institution Name Box -->
              <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-left: 4px solid #0288d1; border-radius: 12px; padding: 10px 14px; margin-bottom: 12px;">
                <div style="color: #0288d1; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 4px;">
                  <span>🏫</span> COLLEGE / INSTITUTION NAME
                </div>
                <div id="cardCollege" style="font-family: 'Space Grotesk', sans-serif; font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                  pscmrcet
                </div>
              </div>

              <!-- Branch & Year 2-Column Grid -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                <!-- Branch -->
                <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-left: 4px solid #0288d1; border-radius: 12px; padding: 10px 12px;">
                  <div style="color: #0288d1; font-size: 10px; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                    <span>🎓</span> BRANCH
                  </div>
                  <div id="cardBranch" style="font-family: 'Space Grotesk', sans-serif; font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                    CSO
                  </div>
                </div>
                <!-- Year -->
                <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-left: 4px solid #0288d1; border-radius: 12px; padding: 10px 12px;">
                  <div style="color: #0288d1; font-size: 10px; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                    <span>📅</span> YEAR OF STUDY
                  </div>
                  <div id="cardYear" style="font-family: 'Space Grotesk', sans-serif; font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                    4th Year
                  </div>
                </div>
              </div>

              <!-- 3. Events Evaluation Checklist Table -->
              <div style="border: 1.5px solid #cbd5e1; border-radius: 12px; overflow: hidden; margin-bottom: 14px;">
                <div style="background: #020d1a; padding: 8px 12px; color: #ffffff; font-size: 11px; font-weight: 800; display: flex; justify-content: space-between; align-items: center;">
                  <span>📋 EVENTS EVALUATION CHECKLIST</span>
                  <span style="color: #00f2fe; font-size: 10px;">INNOWAVE-2K26</span>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px; background: #ffffff;">
                  <thead>
                    <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; color: #475569; font-weight: 800; text-transform: uppercase; font-size: 9.5px;">
                      <th style="padding: 6px 10px; text-align: left;">BROCHURE EVENT NAME</th>
                      <th style="padding: 6px 8px; text-align: center; width: 70px;">MARK [✓]</th>
                      <th style="padding: 6px 10px; text-align: center; width: 110px;">EVENT HEAD SIGN</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                      <td style="padding: 6px 10px; font-weight: 700; color: #0f172a;">🧠 Technical Quiz</td>
                      <td style="padding: 6px 8px; text-align: center;"><div style="width: 16px; height: 16px; border: 1.5px solid #94a3b8; border-radius: 4px; margin: 0 auto;"></div></td>
                      <td style="padding: 6px 10px; text-align: center; color: #cbd5e1; font-weight: 300;">__________________</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                      <td style="padding: 6px 10px; font-weight: 700; color: #0f172a;">💻 Coding Challenge</td>
                      <td style="padding: 6px 8px; text-align: center;"><div style="width: 16px; height: 16px; border: 1.5px solid #94a3b8; border-radius: 4px; margin: 0 auto;"></div></td>
                      <td style="padding: 6px 10px; text-align: center; color: #cbd5e1; font-weight: 300;">__________________</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                      <td style="padding: 6px 10px; font-weight: 700; color: #0f172a;">🗺️ Tech Treasure Hunt</td>
                      <td style="padding: 6px 8px; text-align: center;"><div style="width: 16px; height: 16px; border: 1.5px solid #94a3b8; border-radius: 4px; margin: 0 auto;"></div></td>
                      <td style="padding: 6px 10px; text-align: center; color: #cbd5e1; font-weight: 300;">__________________</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                      <td style="padding: 6px 10px; font-weight: 700; color: #0f172a;">🚀 Project Expo</td>
                      <td style="padding: 6px 8px; text-align: center;"><div style="width: 16px; height: 16px; border: 1.5px solid #94a3b8; border-radius: 4px; margin: 0 auto;"></div></td>
                      <td style="padding: 6px 10px; text-align: center; color: #cbd5e1; font-weight: 300;">__________________</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                      <td style="padding: 6px 10px; font-weight: 700; color: #0f172a;">🤖 Prompt Engineering</td>
                      <td style="padding: 6px 8px; text-align: center;"><div style="width: 16px; height: 16px; border: 1.5px solid #94a3b8; border-radius: 4px; margin: 0 auto;"></div></td>
                      <td style="padding: 6px 10px; text-align: center; color: #cbd5e1; font-weight: 300;">__________________</td>
                    </tr>
                    <tr>
                      <td style="padding: 6px 10px; font-weight: 700; color: #0f172a;">🎬 Reels (1 Min)</td>
                      <td style="padding: 6px 8px; text-align: center;"><div style="width: 16px; height: 16px; border: 1.5px solid #94a3b8; border-radius: 4px; margin: 0 auto;"></div></td>
                      <td style="padding: 6px 10px; text-align: center; color: #cbd5e1; font-weight: 300;">__________________</td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>

            <!-- 4. Bottom Dark Navy Blue Footer Section -->
            <div style="background: #020d1a; padding: 14px 18px; border-top: 2px solid #00c3ff; display: flex; justify-content: space-between; align-items: center;">
              <!-- Left: QR Code Verification Box -->
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: #ffffff; padding: 4px; border-radius: 10px; border: 1.5px solid #00f2fe;">
                  <img id="cardQrImg" src="" style="width: 60px; height: 60px; display: block;">
                </div>
                <div>
                  <div style="color: #00f2fe; font-size: 11px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase;">SCAN TO VERIFY</div>
                  <div style="color: #94a3b8; font-size: 9.5px; font-weight: 600;">Official Pass</div>
                  <div style="color: #ffffff; font-size: 9.5px; font-weight: 700;">InnoWave-2k26</div>
                </div>
              </div>

              <!-- Right: Registration Signature Line -->
              <div style="text-align: right;">
                <div style="border-bottom: 1px solid #64748b; width: 140px; margin-bottom: 4px;"></div>
                <div style="color: #f59e0b; font-size: 10px; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase;">
                  REGISTRATION SIGNATURE
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
        function viewImage(title, src) {
          document.getElementById('imgModalTitle').textContent = title;
          document.getElementById('imgModalSrc').src = src;
          document.getElementById('imgModal').style.display = 'flex';
        }
        function closeImgModal() {
          document.getElementById('imgModal').style.display = 'none';
        }

        function openOfficialIdCardModal(teamId, name, college, branch, year, roll, ieee) {
          if (document.getElementById('cardHeaderTeamId')) document.getElementById('cardHeaderTeamId').textContent = teamId;
          if (document.getElementById('cardNameIdBadge')) document.getElementById('cardNameIdBadge').textContent = 'ID: ' + teamId;
          if (document.getElementById('cardName')) document.getElementById('cardName').textContent = name;
          if (document.getElementById('cardCollege')) document.getElementById('cardCollege').textContent = college || 'PSCMR CET';
          if (document.getElementById('cardBranch')) document.getElementById('cardBranch').textContent = branch || 'N/A';
          if (document.getElementById('cardYear')) document.getElementById('cardYear').textContent = year ? (year.toLowerCase().includes('year') ? year : year + ' Year') : 'N/A';

          const verifyUrl = window.location.origin + window.location.pathname.replace('admin.php', 'verify-id.html') + '?id=' + encodeURIComponent(teamId);
          const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(verifyUrl);
          if (document.getElementById('cardQrImg')) document.getElementById('cardQrImg').src = qrUrl;

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
          const formData = new FormData();
          formData.append('id', id);
          formData.append('action', action);
          try {
            const res = await fetch('api/admin-action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data && data.ok) {
              window.location.reload();
            } else {
              alert(data.error || 'Action failed.');
            }
          } catch (err) {
            console.error(err);
            alert('Network error.');
          }
        }
      </script>

    <?php endif; ?>

  </div>
</body>

</html>