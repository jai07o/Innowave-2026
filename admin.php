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
  
  @media print {
    body * { visibility: hidden; }
    #printableIdCard, #printableIdCard * { visibility: visible; }
    #printableIdCard { position: absolute; left: 0; top: 0; width: 100%; }
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
    <div>
      <a href="admin.php?action=logout" class="btn btn-delete" style="text-decoration:none; padding:8px 16px;">🔒 Logout</a>
    </div>
  </div>

  <!-- Official Bank Account Banner -->
  <div style="background:linear-gradient(135deg, rgba(0, 242, 254, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%); border:1.5px solid var(--accent); border-radius:16px; padding:16px 20px; margin-bottom:24px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
    <div>
      <div style="font-size:11px; font-weight:800; color:var(--accent); text-transform:uppercase; letter-spacing:0.08em;">OFFICIAL BENEFICIARY ACCOUNT</div>
      <div style="font-family:'Space Grotesk',sans-serif; font-size:15px; font-weight:800; color:#ffffff; margin-top:2px;">POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE</div>
    </div>
    <div style="display:flex; gap:16px;">
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
                <div style="font-weight:900; font-size:13px; margin-top:4px; color:#00e676;">₹<?= esc($r['amount'] ?? 100) ?></div>
              </td>
              <td>
                <strong><?= $nameEsc ?></strong><br>
                <a href="mailto:<?= esc($r['leader_email']) ?>" style="color:#00f2fe; text-decoration:none; font-size:12px;"><?= esc($r['leader_email']) ?></a><br>
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
    <div class="modal-content" onclick="event.stopPropagation()" style="max-width:460px; background:#040914; border:2px solid var(--accent); border-radius:24px; padding:24px; text-align:center;">
      <h3 style="color:var(--gold); font-size:18px; margin-bottom:14px; font-family:'Space Grotesk',sans-serif;">🪪 OFFICIAL PARTICIPANT ID CARD PRINTER</h3>
      
      <!-- Printable Card Container -->
      <div id="printableIdCard" style="background:linear-gradient(135deg, #09152e 0%, #030814 100%); border:2px solid #00f2fe; border-radius:20px; padding:24px 18px; color:#ffffff; text-align:center; position:relative; box-shadow:0 0 30px rgba(0,242,254,0.25); margin-bottom:16px;">
        <div style="font-size:10px; font-weight:800; color:#fbbf24; text-transform:uppercase; letter-spacing:0.12em; margin-bottom:4px;">
          POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE OF ENGG & TECH
        </div>
        <div style="font-family:'Space Grotesk',sans-serif; font-size:18px; font-weight:900; color:#00f2fe; margin-bottom:12px;">
          INNOWAVE-2K26 DELEGATE PASS
        </div>

        <div style="width:70px; height:70px; margin:0 auto 12px; background:linear-gradient(135deg, #00f2fe, #0066ff); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:32px; box-shadow:0 0 15px rgba(0,242,254,0.4);">
          🎓
        </div>

        <div id="cardName" style="font-family:'Space Grotesk',sans-serif; font-size:20px; font-weight:900; color:#ffffff; margin-bottom:4px;">
          Participant Name
        </div>
        <div id="cardCollege" style="font-size:12.5px; color:#94a3b8; font-weight:600; margin-bottom:10px;">
          PSCMR CET · CSE (III Year)
        </div>

        <div style="background:rgba(0, 230, 118, 0.15); border:1px solid #00e676; border-radius:10px; padding:6px 12px; display:inline-block; font-size:11px; font-weight:900; color:#00e676; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:14px;">
          🟢 OFFICIAL VERIFIED PARTICIPANT
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; background:rgba(255,255,255,0.04); padding:12px; border-radius:12px; font-size:12px; text-align:left; border:1px solid rgba(255,255,255,0.1);">
          <div>
            <span style="color:#94a3b8; font-size:10px; display:block; font-weight:700;">TEAM REG ID</span>
            <strong id="cardTeamId" style="color:#00f2fe; font-size:15px; font-family:monospace;">IW26-1001</strong>
          </div>
          <div>
            <span style="color:#94a3b8; font-size:10px; display:block; font-weight:700;">MEMBERSHIP</span>
            <strong id="cardIeee" style="color:#fbbf24; font-size:12px;">IEEE Member</strong>
          </div>
          <div style="grid-column: span 2;">
            <span style="color:#94a3b8; font-size:10px; display:block; font-weight:700;">ROLL NUMBER</span>
            <strong id="cardRollNo" style="color:#ffffff; font-size:12px;">22KT1A0501</strong>
          </div>
        </div>

        <div style="font-size:10px; color:#64748b; margin-top:12px; font-weight:700;">
          STB18301 · IEEE Student Branch · Organizers Entry Pass
        </div>
      </div>

      <div style="display:flex; gap:10px;">
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

    function openOfficialIdCardModal(teamId, name, college, branch, year, roll, ieee) {
      document.getElementById('cardTeamId').textContent = teamId;
      document.getElementById('cardName').textContent = name;
      document.getElementById('cardCollege').textContent = college + (branch ? ' · ' + branch : '') + (year ? ' (' + year + ')' : '');
      document.getElementById('cardRollNo').textContent = roll || 'N/A';
      document.getElementById('cardIeee').textContent = ieee;
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
