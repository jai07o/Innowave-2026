<?php
/**
 * INNOWAVE-2K26 — Database Connection & Schema Initialization (PHP + SQLite PDO)
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$dataDir = __DIR__ . '/../data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$dbPath = $dataDir . '/innowave.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA synchronous = NORMAL;");

    // Initialize registrations table
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

    // Tiny migration helper for missing columns
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

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
