<?php
/**
 * INNOWAVE-2K26 — High-Performance Production MySQL Database Engine
 *
 * Designed for ultra-fast response times (<2ms), zero overhead, and resilience
 * across cPanel shared hosting, local development, VPS, and Docker containers.
 */

// 1. Send CORS and Security Headers
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Password');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// 2. Load Configuration Constants
require_once __DIR__ . '/config.php';

$cfg_host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
$cfg_name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'innowave_db');
$cfg_user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
$cfg_pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$cfg_port = defined('DB_PORT') && DB_PORT ? (int)DB_PORT : (getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);

$pdo = null;
$activeEngine = 'mysql';
$lastMysqlError = '';

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 2,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

// 3. Fast Primary Connection Attempt (<5ms)
try {
    $dsn = "mysql:host={$cfg_host};port={$cfg_port};dbname={$cfg_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg_user, $cfg_pass, $pdoOptions);
    if ($pdo) {
        $activeEngine = 'mysql';
    }
} catch (Exception $e) {
    $lastMysqlError = $e->getMessage();
}

// 3b. Try 127.0.0.1 if localhost socket connection failed
if (!$pdo && ($cfg_host === 'localhost' || $cfg_host === '127.0.0.1')) {
    $altHost = ($cfg_host === 'localhost') ? '127.0.0.1' : 'localhost';
    try {
        $dsn = "mysql:host={$altHost};port={$cfg_port};dbname={$cfg_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg_user, $cfg_pass, $pdoOptions);
        if ($pdo) {
            $cfg_host = $altHost;
            $activeEngine = 'mysql';
        }
    } catch (Exception $eAlt) {
        $lastMysqlError .= " | AltHost({$altHost}): " . $eAlt->getMessage();
    }
}

// 3c. If database does not exist, auto-create innowave_db on the MySQL server
if (!$pdo && strpos($lastMysqlError, 'Unknown database') !== false) {
    try {
        $rootPdo = new PDO("mysql:host={$cfg_host};port={$cfg_port};charset=utf8mb4", $cfg_user, $cfg_pass, $pdoOptions);
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host={$cfg_host};port={$cfg_port};dbname={$cfg_name};charset=utf8mb4", $cfg_user, $cfg_pass, $pdoOptions);
        if ($pdo) {
            $activeEngine = 'mysql';
        }
    } catch (Exception $eDb) {
        $lastMysqlError .= " | AutoCreateDb: " . $eDb->getMessage();
    }
}

// 3d. Try Unix Socket directly on cPanel / Linux (e.g. /var/lib/mysql/mysql.sock)
if (!$pdo) {
    $commonSockets = [
        '/var/lib/mysql/mysql.sock',
        '/tmp/mysql.sock',
        '/run/mysqld/mysqld.sock',
        '/var/run/mysqld/mysqld.sock'
    ];
    foreach ($commonSockets as $sock) {
        if (@file_exists($sock)) {
            try {
                $dsn = "mysql:unix_socket={$sock};dbname={$cfg_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $cfg_user, $cfg_pass, $pdoOptions);
                if ($pdo) {
                    $activeEngine = 'mysql';
                    break;
                }
            } catch (Exception $sockEx) {
                $lastMysqlError .= " | Socket({$sock}): " . $sockEx->getMessage();
            }
        }
    }
}

// 3e. Check if cPanel database name is prefixed with cPanel username
if (!$pdo) {
    $sysUser = '';
    if (!empty($_SERVER['DOCUMENT_ROOT']) && preg_match('#/home[0-9]*/([^/]+)#', $_SERVER['DOCUMENT_ROOT'], $m)) {
        $sysUser = $m[1];
    }
    if (empty($sysUser) && function_exists('get_current_user')) {
        $sysUser = @get_current_user();
    }
    if (!empty($sysUser) && $sysUser !== $cfg_user) {
        $candidateDbs = [
            $sysUser . '_' . $cfg_name,
            $sysUser . '_innowave',
            $sysUser . '_innowave_db',
            $sysUser . '_inno'
        ];
        foreach ($candidateDbs as $cdb) {
            try {
                $dsn = "mysql:host={$cfg_host};port={$cfg_port};dbname={$cdb};charset=utf8mb4";
                $pdo = new PDO($dsn, $sysUser, $cfg_pass, $pdoOptions);
                if ($pdo) {
                    $cfg_name = $cdb;
                    $cfg_user = $sysUser;
                    $activeEngine = 'mysql';
                    break;
                }
            } catch (Exception $cpEx) {}
        }
    }
}

// 4. Schema Verification & Auto-Healing (100% Pure MySQL)
if ($pdo) {
    $tableNeedsInit = false;
    try {
        $checkStmt = $pdo->query("SELECT 1 FROM `registrations` LIMIT 1");
    } catch (Exception $e) {
        $tableNeedsInit = true;
    }

    if ($tableNeedsInit) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `registrations` (
                    `id`                      INT AUTO_INCREMENT PRIMARY KEY,
                    `team_id`                 VARCHAR(100) UNIQUE,
                    `reg_seq`                 INT DEFAULT 1,
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
                    `ieee_member`             VARCHAR(10) NOT NULL DEFAULT 'No',
                    `ieee_id`                 VARCHAR(100),
                    `ieee_card`               LONGTEXT,
                    `ieee_verification_status` VARCHAR(100) DEFAULT 'N/A',
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
        } catch (Exception $e) {}
    } else {
        // Auto-heal missing columns if an older database table was imported
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM `registrations`");
            $existingCols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $colsLower = array_map('strtolower', $existingCols);

            $requiredCols = [
                'reg_seq' => "ALTER TABLE `registrations` ADD COLUMN `reg_seq` INT NOT NULL DEFAULT 1",
                'ieee_card' => "ALTER TABLE `registrations` ADD COLUMN `ieee_card` LONGTEXT DEFAULT NULL",
                'ieee_verification_status' => "ALTER TABLE `registrations` ADD COLUMN `ieee_verification_status` VARCHAR(100) DEFAULT 'N/A'",
                'ieee_email' => "ALTER TABLE `registrations` ADD COLUMN `ieee_email` VARCHAR(255) DEFAULT NULL",
                'ieee_grade' => "ALTER TABLE `registrations` ADD COLUMN `ieee_grade` VARCHAR(100) DEFAULT NULL",
                'ieee_count' => "ALTER TABLE `registrations` ADD COLUMN `ieee_count` INT DEFAULT 0",
                'non_ieee_count' => "ALTER TABLE `registrations` ADD COLUMN `non_ieee_count` INT DEFAULT 0",
                'payment_screenshot' => "ALTER TABLE `registrations` ADD COLUMN `payment_screenshot` LONGTEXT DEFAULT NULL",
                'payment_proof' => "ALTER TABLE `registrations` ADD COLUMN `payment_proof` LONGTEXT DEFAULT NULL",
                'duplicate_utr' => "ALTER TABLE `registrations` ADD COLUMN `duplicate_utr` INT DEFAULT 0",
                'utr_mismatch' => "ALTER TABLE `registrations` ADD COLUMN `utr_mismatch` INT DEFAULT 0",
                'utr_warning' => "ALTER TABLE `registrations` ADD COLUMN `utr_warning` VARCHAR(255) DEFAULT NULL",
                'ieee_ocr_mismatch' => "ALTER TABLE `registrations` ADD COLUMN `ieee_ocr_mismatch` INT DEFAULT 0",
                'ieee_warning' => "ALTER TABLE `registrations` ADD COLUMN `ieee_warning` VARCHAR(255) DEFAULT NULL",
                'paid_at' => "ALTER TABLE `registrations` ADD COLUMN `paid_at` VARCHAR(100) DEFAULT NULL"
            ];

            foreach ($requiredCols as $cName => $alterSql) {
                if (!in_array(strtolower($cName), $colsLower)) {
                    @$pdo->exec($alterSql);
                }
            }
        } catch (Exception $colEx) {}
    }
}
