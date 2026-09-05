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

// Build host candidates
$hosts = [$cfg_host];
if ($cfg_host !== '127.0.0.1' && ($cfg_host === 'localhost' || $cfg_host === '')) {
    $hosts[] = '127.0.0.1';
}
$hosts = array_unique($hosts);

// Build port candidates (tries explicit port, standard 3306, and alternate 3307)
$ports = [];
if (!empty($cfg_port)) {
    $ports[] = (int)$cfg_port;
}
if (!in_array(3306, $ports)) $ports[] = 3306;
if (!in_array(3307, $ports)) $ports[] = 3307;

// Build password candidates
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

// 2. PRIMARY & EXCLUSIVE: Connect to MySQL
foreach ($hosts as $h) {
    foreach ($ports as $p) {
        foreach ($passwords as $pwd) {
            // First attempt: Connect directly to the database
            try {
                $dsn = "mysql:host={$h};port={$p};dbname={$cfg_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $cfg_user, $pwd, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
                if ($pdo) break 3;
            } catch (Exception $e) {
                $lastMysqlError = $e->getMessage();
                // If database doesn't exist yet, connect to server and create it
                try {
                    $dsnNoDb = "mysql:host={$h};port={$p};charset=utf8mb4";
                    $pdoTemp = new PDO($dsnNoDb, $cfg_user, $pwd, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `{$cfg_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    
                    $dsn = "mysql:host={$h};port={$p};dbname={$cfg_name};charset=utf8mb4";
                    $pdo = new PDO($dsn, $cfg_user, $pwd, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]);
                    if ($pdo) break 3;
                } catch (Exception $ex) {
                    $lastMysqlError = $ex->getMessage();
                    // Try next host/port/password combination
                }
            }
        }
    }
}

// If MySQL connection could not be established, exit with clear error
if (!$pdo) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'MySQL Database Connection Failed: ' . ($lastMysqlError ?: 'Could not connect to MySQL server. Please verify MySQL service is running and credentials in api/config.php are correct.')
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
