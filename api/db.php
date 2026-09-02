<?php
/**
 * INNOWAVE-2K26 — Pure MySQL Database Engine & Auto-Schema Initialization
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configurable MySQL Database Credentials (Set these on your web host / cPanel)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'innowave_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_port = getenv('DB_PORT') ?: '3306';

$passCandidates = [];
if (getenv('DB_PASS') !== false && getenv('DB_PASS') !== '') {
    $passCandidates[] = getenv('DB_PASS');
}
$passCandidates[] = 'innowave2k26';
$passCandidates[] = 'innowave2026';
$passCandidates[] = '';
$passCandidates[] = 'root';
$passCandidates[] = 'password';
$passCandidates[] = '123456';

$pdo = null;
$lastError = null;

foreach ($passCandidates as $testPass) {
    try {
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $testPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        if ($pdo) break;
    } catch (Exception $e) {
        $lastError = $e;
        // Try creating DB if missing
        try {
            $dsnNoDb = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
            $pdoTemp = new PDO($dsnNoDb, $db_user, $testPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            
            $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $testPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            if ($pdo) break;
        } catch (Exception $ex) {
            $lastError = $ex;
        }
    }
}

if (!$pdo) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'MySQL Database Connection Error: ' . ($lastError ? $lastError->getMessage() : 'Could not connect to MySQL server.')]);
    exit;
}

// Auto-initialize MySQL Registrations Table Schema
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
        `created_at`              VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Column Migration Helper to auto-add missing columns to existing MySQL tables
function ensureMysqlColumn($pdo, $columnName, $definition) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `registrations` LIKE '{$columnName}'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `registrations` ADD COLUMN `{$columnName}` {$definition}");
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

