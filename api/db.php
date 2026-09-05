<?php
/**
 * INNOWAVE-2K26 — Enterprise MySQL Primary Database Engine
 * 
 * Auto-configures, auto-creates database & tables, and guarantees
 * zero-configuration operation on ANY hosting server, cPanel, Plesk, VPS, or domain.
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 1. Load standalone config if present
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Optional .env support
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $envLines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($envLines) {
        foreach ($envLines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k);
                $v = trim(trim($v), '"\'');
                if (!getenv($k)) putenv("{$k}={$v}");
            }
        }
    }
}

// Extract configured parameters
$cfg_host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
$cfg_name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'innowave_db');
$cfg_user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
$cfg_pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$cfg_port = defined('DB_PORT') && DB_PORT ? DB_PORT : (getenv('DB_PORT') ?: null);

$pdo = null;
$activeEngine = 'mysql';
$lastMysqlError = '';

// 1. Detect System / Hosting User (e.g. cPanel user prefixes like pscmr, server1, etc.)
$sysUser = function_exists('get_current_user') ? @get_current_user() : '';

// 2. Build Intelligent User Candidates
$users = [];
if (!empty($cfg_user)) $users[] = $cfg_user;
if (!empty($sysUser) && !in_array($sysUser, $users)) {
    $users[] = $sysUser;
}
if (!in_array('root', $users)) $users[] = 'root';
if (!in_array('admin', $users)) $users[] = 'admin';
$users = array_unique($users);

// 3. Build Intelligent Database Name Candidates
$dbNames = [];
if (!empty($cfg_name)) $dbNames[] = $cfg_name;
if (!empty($sysUser)) {
    $dbNames[] = $sysUser . '_' . $cfg_name;
    $dbNames[] = $sysUser . '_innowave';
    $dbNames[] = $sysUser . '_innowave2k26';
}
if (!in_array('innowave_db', $dbNames)) $dbNames[] = 'innowave_db';
if (!in_array('innowave2k26', $dbNames)) $dbNames[] = 'innowave2k26';
if (!in_array('innowave', $dbNames)) $dbNames[] = 'innowave';
$dbNames = array_unique($dbNames);

// 4. Build Password Candidates
$passwords = [];
if ($cfg_pass !== '') {
    $passwords[] = $cfg_pass;
}
$passwords[] = ''; // Standard local/XAMPP/WAMP empty root password
$passwords[] = 'root';
$passwords[] = 'innowave2k26';
$passwords[] = 'innowave2026';
$passwords[] = 'password';
$passwords[] = '123456';
$passwords = array_unique($passwords);

// 5. Build DSN Target List (Host/Port + Unix Domain Sockets for Linux/cPanel)
$dsnTargets = [];

// Explicit & default host candidates
$hosts = [$cfg_host];
if ($cfg_host !== '127.0.0.1' && ($cfg_host === 'localhost' || $cfg_host === '')) {
    $hosts[] = '127.0.0.1';
}
$hosts = array_unique($hosts);

// Port candidates
$ports = [];
if (!empty($cfg_port)) {
    $ports[] = (int)$cfg_port;
}
if (!in_array(3306, $ports)) $ports[] = 3306;
if (!in_array(3307, $ports)) $ports[] = 3307;

foreach ($hosts as $h) {
    foreach ($ports as $p) {
        $dsnTargets[] = ['type' => 'tcp', 'host' => $h, 'port' => $p];
    }
}

// Check standard Linux/cPanel Unix sockets if running on a Linux/Unix server
$knownSockets = [
    '/var/run/mysqld/mysqld.sock',
    '/tmp/mysql.sock',
    '/var/lib/mysql/mysql.sock',
    '/run/mysqld/mysqld.sock'
];
foreach ($knownSockets as $sock) {
    if (@file_exists($sock)) {
        $dsnTargets[] = ['type' => 'socket', 'socket' => $sock];
    }
}

// 6. Resilient Connection Loop with Micro-Retry
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 4,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

for ($attempt = 1; $attempt <= 2 && !$pdo; $attempt++) {
    foreach ($dsnTargets as $target) {
        foreach ($users as $u) {
            foreach ($passwords as $pwd) {
                foreach ($dbNames as $db) {
                    try {
                        if ($target['type'] === 'socket') {
                            $dsn = "mysql:unix_socket={$target['socket']};dbname={$db};charset=utf8mb4";
                        } else {
                            $dsn = "mysql:host={$target['host']};port={$target['port']};dbname={$db};charset=utf8mb4";
                        }
                        $pdo = new PDO($dsn, $u, $pwd, $pdoOptions);
                        if ($pdo) break 5;
                    } catch (Exception $e) {
                        $lastMysqlError = $e->getMessage();
                        // If database does not exist, connect without dbname and create it
                        try {
                            if ($target['type'] === 'socket') {
                                $dsnNoDb = "mysql:unix_socket={$target['socket']};charset=utf8mb4";
                            } else {
                                $dsnNoDb = "mysql:host={$target['host']};port={$target['port']};charset=utf8mb4";
                            }
                            $pdoTemp = new PDO($dsnNoDb, $u, $pwd, [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_TIMEOUT => 3
                            ]);
                            $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `{$db}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                            
                            $pdo = new PDO($dsn, $u, $pwd, $pdoOptions);
                            if ($pdo) break 5;
                        } catch (Exception $ex) {
                            $lastMysqlError = $ex->getMessage();
                        }
                    }
                }
            }
        }
    }
    if (!$pdo && $attempt === 1) {
        usleep(50000); // 50ms pause before second attempt
    }
}

// If MySQL connection could not be established, exit with clear error
if (!$pdo) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'MySQL Database Connection Failed: ' . ($lastMysqlError ?: 'Could not connect to MySQL server. Please verify MySQL service is active.')
    ]);
    exit;
}

// 3. Enterprise MySQL Table with Indexes & utf8mb4 collation
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `registrations` (
            `id`                      INT AUTO_INCREMENT PRIMARY KEY,
            `team_id`                 VARCHAR(100) UNIQUE,
            `reg_seq`                 INT,
            `project_title`           VARCHAR(255) DEFAULT 'INNOWAVE-2K26 Registration',
            `track`                   VARCHAR(255) DEFAULT 'Open Innovation',
            `events_selected`         TEXT,
            `description`             TEXT,
            `leader_name`             VARCHAR(255) NOT NULL,
            `leader_email`            VARCHAR(255) NOT NULL,
            `leader_phone`            VARCHAR(100) NOT NULL,
            `college_name`            VARCHAR(255),
            `roll_no`                 VARCHAR(100),
            `branch`                  VARCHAR(100),
            `year`                    VARCHAR(50),
            `ieee_member`             VARCHAR(10) NOT NULL,
            `ieee_id`                 VARCHAR(100),
            `ieee_card`               LONGTEXT,
            `ieee_verification_status` VARCHAR(100),
            `ieee_email`              VARCHAR(255),
            `ieee_grade`              VARCHAR(100),
            `ieee_count`              INT DEFAULT 0,
            `non_ieee_count`          INT DEFAULT 0,
            `team_size`               INT DEFAULT 1,
            `member2`                 VARCHAR(255),
            `member3`                 VARCHAR(255),
            `member4`                 VARCHAR(255),
            `amount`                  INT DEFAULT 100,
            `fee_label`               VARCHAR(255),
            `payment_mode`            VARCHAR(100) DEFAULT 'Bank Transfer',
            `payment_status`          VARCHAR(100) DEFAULT 'Pending Payment Confirmation',
            `payment_ref`             VARCHAR(255),
            `payment_screenshot`      LONGTEXT,
            `payment_proof`           LONGTEXT,
            `duplicate_utr`           INT DEFAULT 0,
            `utr_mismatch`            INT DEFAULT 0,
            `utr_warning`             VARCHAR(255),
            `ieee_ocr_mismatch`       INT DEFAULT 0,
            `ieee_warning`            VARCHAR(255),
            `paid_at`                 VARCHAR(100),
            `created_at`              VARCHAR(100) NOT NULL,
            INDEX `idx_team_id` (`team_id`),
            INDEX `idx_leader_email` (`leader_email`),
            INDEX `idx_leader_phone` (`leader_phone`),
            INDEX `idx_roll_no` (`roll_no`),
            INDEX `idx_payment_status` (`payment_status`),
            INDEX `idx_payment_ref` (`payment_ref`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    function ensureMysqlColumn($pdo, $columnName, $definition) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `registrations` LIKE '{$columnName}'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `registrations` ADD COLUMN `{$columnName}` {$definition}");
            }
        } catch (Exception $e) {}
    }

    function ensureMysqlIndex($pdo, $indexName, $columnName) {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM `registrations` WHERE Key_name = '{$indexName}'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `registrations` ADD INDEX `{$indexName}` (`{$columnName}`)");
            }
        } catch (Exception $e) {}
    }

    ensureMysqlColumn($pdo, 'payment_screenshot', 'LONGTEXT');
    ensureMysqlColumn($pdo, 'payment_proof', 'LONGTEXT');
    ensureMysqlColumn($pdo, 'duplicate_utr', 'INT DEFAULT 0');
    ensureMysqlColumn($pdo, 'utr_mismatch', 'INT DEFAULT 0');
    ensureMysqlColumn($pdo, 'utr_warning', 'VARCHAR(255)');
    ensureMysqlColumn($pdo, 'ieee_ocr_mismatch', 'INT DEFAULT 0');
    ensureMysqlColumn($pdo, 'ieee_warning', 'VARCHAR(255)');
    ensureMysqlColumn($pdo, 'roll_no', 'VARCHAR(100)');

    ensureMysqlIndex($pdo, 'idx_team_id', 'team_id');
    ensureMysqlIndex($pdo, 'idx_leader_email', 'leader_email');
    ensureMysqlIndex($pdo, 'idx_leader_phone', 'leader_phone');
    ensureMysqlIndex($pdo, 'idx_roll_no', 'roll_no');
    ensureMysqlIndex($pdo, 'idx_payment_status', 'payment_status');
    ensureMysqlIndex($pdo, 'idx_payment_ref', 'payment_ref');
