<?php
/**
 * INNOWAVE-2K26 — Production Database & Server Configuration
 *
 * This configuration ensures seamless deployment on ANY server, domain,
 * cPanel, Plesk, Linux VPS, Docker, or local environment without requiring code modifications.
 *
 * It automatically detects:
 * 1. .env files located in api/, public/, or root.
 * 2. Environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT).
 * 3. Default fallback credentials for local & cPanel hosting.
 */

// Helper to safely fetch environment or server variables across any host
function getEnvVar($key, $default = '') {
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (function_exists('getenv')) {
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
    }
    return $default;
}

// Auto-load .env file if present
$envPaths = [
    __DIR__ . '/.env',
    dirname(__DIR__) . '/.env',
    dirname(dirname(__DIR__)) . '/.env'
];
foreach ($envPaths as $envFile) {
    if (file_exists($envFile) && is_readable($envFile)) {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if (!isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                    if (function_exists('putenv')) @putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
        break;
    }
}

// Production MySQL Database Credentials
// Edit below or set in your .env / cPanel environment if your host requires custom credentials
if (!defined('DB_HOST')) define('DB_HOST', getEnvVar('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', getEnvVar('DB_NAME', 'innowave_db'));
if (!defined('DB_USER')) define('DB_USER', getEnvVar('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', getEnvVar('DB_PASS', ''));
if (!defined('DB_PORT')) define('DB_PORT', getEnvVar('DB_PORT', 3306));

// Admin Portal Master Passcode
if (!defined('ADMIN_PASSCODE')) define('ADMIN_PASSCODE', getEnvVar('ADMIN_PASSCODE', 'innowave2k26'));

