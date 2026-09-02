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
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_port = getenv('DB_PORT') ?: '3306';

$pdo = null;

try {
    // 1. Connect to MySQL Database
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (Exception $e) {
    // 2. Auto-create MySQL database if it doesn't exist yet on MySQL server
    try {
        $dsnNoDb = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
        $pdoTemp = new PDO($dsnNoDb, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
    } catch (Exception $ex) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'MySQL Database Connection Error: ' . $ex->getMessage()]);
        exit;
    }
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
        `payment_proof`           LONGTEXT,
        `paid_at`                 VARCHAR(100),
        `created_at`              VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
