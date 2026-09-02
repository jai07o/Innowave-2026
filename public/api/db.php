<?php
/**
 * INNOWAVE-2K26 — Database Connection & Schema Initialization (PHP + MySQL PDO with SQLite Fallback)
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
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_port = getenv('DB_PORT') ?: '3306';

$isMysql = false;
$pdo = null;

// 1. Try MySQL Connection First
try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $isMysql = true;
} catch (Exception $e) {
    // 2. Fallback to SQLite PDO if MySQL database server is not configured locally
    $dataDir = __DIR__ . '/../data';
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    $dbPath = $dataDir . '/innowave.db';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA synchronous = NORMAL;");
}

// Auto-initialize MySQL or SQLite Registrations Table Schema
if ($isMysql) {
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
            `payment_proof`           LONGTEXT,
            `paid_at`                 VARCHAR(100),
            `created_at`              VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS registrations (
            id                      INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id                 TEXT UNIQUE,
            reg_seq                 INTEGER,
            project_title           TEXT DEFAULT 'INNOWAVE-2K26 Registration',
            track                   TEXT DEFAULT 'Open Innovation',
            events_selected         TEXT,
            description             TEXT DEFAULT '',
            leader_name             TEXT NOT NULL,
            leader_email            TEXT NOT NULL,
            leader_phone            TEXT NOT NULL,
            college_name            TEXT,
            roll_no                 TEXT,
            branch                  TEXT,
            year                    TEXT,
            ieee_member             TEXT NOT NULL,
            ieee_id                 TEXT,
            ieee_card               TEXT,
            ieee_verification_status TEXT,
            ieee_email              TEXT,
            ieee_grade              TEXT,
            ieee_count              INTEGER DEFAULT 0,
            non_ieee_count          INTEGER DEFAULT 0,
            team_size               INTEGER DEFAULT 1,
            member2                 TEXT,
            member3                 TEXT,
            member4                 TEXT,
            amount                  INTEGER DEFAULT 100,
            fee_label               TEXT,
            payment_mode            TEXT DEFAULT 'Bank Transfer',
            payment_status          TEXT DEFAULT 'Pending Payment Confirmation',
            payment_ref             TEXT,
            payment_proof           TEXT,
            paid_at                 TEXT,
            created_at              TEXT NOT NULL
        )
    ");

    // Tiny migration helper for missing columns in SQLite
    $colsStmt = $pdo->query("PRAGMA table_info(registrations)");
    $existingCols = [];
    while ($col = $colsStmt->fetch()) {
        $existingCols[] = $col['name'];
    }

    $requiredCols = [
        'team_id' => 'TEXT',
        'reg_seq' => 'INTEGER',
        'events_selected' => 'TEXT',
        'college_name' => 'TEXT',
        'roll_no' => 'TEXT',
        'branch' => 'TEXT',
        'year' => 'TEXT',
        'ieee_member' => 'TEXT',
        'ieee_id' => 'TEXT',
        'ieee_card' => 'TEXT',
        'ieee_verification_status' => 'TEXT',
        'payment_proof' => 'TEXT',
        'payment_ref' => 'TEXT',
        'payment_status' => 'TEXT',
        'paid_at' => 'TEXT',
        'amount' => 'INTEGER',
        'fee_label' => 'TEXT'
    ];

    foreach ($requiredCols as $col => $type) {
        if (!in_array($col, $existingCols)) {
            $pdo->exec("ALTER TABLE registrations ADD COLUMN {$col} {$type}");
        }
    }
}
