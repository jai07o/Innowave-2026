<?php
/**
 * INNOWAVE-2K26 — Real-Time MySQL Database Diagnostic Probe
 * Open directly in browser: https://your-domain/api/db-probe.php
 */
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/db.php';

$isJson = isset($_GET['json']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

$probe = [
    'time' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql')
    ],
    'connected' => ($pdo !== null),
    'engine' => 'MySQL',
    'db_name' => $cfg_name ?? '',
    'db_user' => $cfg_user ?? '',
    'db_host' => $cfg_host ?? '',
    'db_port' => $cfg_port ?? 3306,
    'last_error' => $lastMysqlError ?? '',
    'table_exists' => false,
    'total_registrations' => 0
];

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `registrations`");
        $probe['table_exists'] = true;
        $probe['total_registrations'] = intval($stmt->fetchColumn());
    } catch (Exception $e) {
        $probe['table_error'] = $e->getMessage();
    }
}

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($probe, JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MySQL Database Probe — InnoWave-2K26</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#04091a; color:#f1f5f9; padding:30px 20px; }
  .box { max-width:680px; margin:0 auto; background:rgba(255,255,255,0.04); border:1.5px solid rgba(0,242,254,0.3); border-radius:16px; padding:24px; box-shadow:0 20px 50px rgba(0,0,0,0.6); }
  h1 { font-size:20px; color:#00f2fe; margin-top:0; display:flex; align-items:center; gap:8px; }
  .status { font-size:17px; font-weight:800; padding:12px 18px; border-radius:10px; margin:16px 0; }
  .status.pass { background:rgba(0,230,118,0.15); border:1.5px solid #00e676; color:#00e676; }
  .status.fail { background:rgba(239,68,68,0.15); border:1.5px solid #ef4444; color:#ef4444; }
  table { width:100%; border-collapse:collapse; margin-top:16px; font-size:13.5px; }
  td { padding:9px 12px; border-bottom:1px solid rgba(255,255,255,0.08); }
  td:first-child { color:#94a3b8; font-weight:600; width:38%; }
  td:last-child { color:#ffffff; font-family:monospace; font-weight:700; }
  .btn { display:inline-block; margin-top:16px; padding:9px 18px; background:#00f2fe; color:#04091a; border-radius:8px; text-decoration:none; font-weight:800; font-size:12px; }
  .tip-box { margin-top:20px; padding:14px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.1); border-radius:10px; font-size:12.5px; color:#cbd5e1; }
</style>
</head>
<body>
<div class="box">
  <h1>🐬 MySQL Live Connectivity Probe</h1>
  
  <?php if ($probe['connected']): ?>
    <div class="status pass">✅ CONNECTED TO MYSQL (<?= $probe['total_registrations'] ?> Registrations in Database)</div>
  <?php else: ?>
    <div class="status fail">❌ MYSQL CONNECTION FAILED</div>
  <?php endif; ?>

  <table>
    <tr><td>Database Engine</td><td>MySQL (Strict)</td></tr>
    <tr><td>Database Name</td><td><?= htmlspecialchars($probe['db_name']) ?></td></tr>
    <tr><td>Database User</td><td><?= htmlspecialchars($probe['db_user']) ?></td></tr>
    <tr><td>Host / Port</td><td><?= htmlspecialchars($probe['db_host']) ?>:<?= $probe['db_port'] ?></td></tr>
    <tr><td>Table 'registrations'</td><td><?= $probe['table_exists'] ? '✅ ACTIVE' : '❌ NOT CREATED' ?></td></tr>
    <tr><td>Total Stored Rows</td><td><?= $probe['total_registrations'] ?></td></tr>
    <tr><td>PHP Version</td><td><?= htmlspecialchars($probe['php_version']) ?></td></tr>
    <tr><td>PHP PDO MySQL Driver</td><td><?= $probe['extensions']['pdo_mysql'] ? '✅ INSTALLED' : '❌ MISSING (Enable pdo_mysql in cPanel PHP Selector)' ?></td></tr>
    <?php if (!empty($probe['last_error'])): ?>
      <tr><td>MySQL Error Message</td><td style="color:#fca5a5; word-break:break-all;"><?= htmlspecialchars($probe['last_error']) ?></td></tr>
    <?php endif; ?>
  </table>

  <?php if (!$probe['connected']): ?>
    <div class="tip-box">
      <strong>💡 MySQL Configuration Help:</strong><br>
      Please check <code>public/api/config.php</code> or create a <code>.env</code> file with your cPanel MySQL credentials:
      <pre style="background:rgba(0,0,0,0.4); padding:8px; border-radius:6px; margin:8px 0; color:#38bdf8;">
DB_HOST=localhost
DB_NAME=pscmr_innowave_db
DB_USER=pscmr_dbuser
DB_PASS=YourDatabasePassword
DB_PORT=3306</pre>
    </div>
  <?php endif; ?>

  <div style="margin-top:20px; display:flex; gap:10px;">
    <a href="db-probe.php" class="btn">↻ Refresh Probe</a>
    <a href="../admin.php" class="btn" style="background:transparent; border:1px solid #00f2fe; color:#00f2fe;">Go to Admin Portal</a>
  </div>
</div>
</body>
</html>
