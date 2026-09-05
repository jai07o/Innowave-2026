<?php
/**
 * INNOWAVE-2K26 — Production Database & Server Configuration
 *
 * This configuration ensures seamless deployment on ANY server, domain,
 * cPanel, Plesk, Linux VPS, Docker, or local environment without requiring code modifications.
 *
 * How it works:
 * 1. Checks environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT).
 * 2. If environment variables are not set, falls back to the constants defined below.
 * 3. Automatically attempts connections on ports 3306 and 3307.
 * 4. Automatically auto-creates the database (`innowave_db`) and tables if not already present.
 */

// Production MySQL Credentials (edit below if your host assigns specific database credentials)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'innowave_db');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: null); // null = auto-try 3306 and 3307

// Admin Portal Master Passcode
if (!defined('ADMIN_PASSCODE')) define('ADMIN_PASSCODE', getenv('ADMIN_PASSCODE') ?: 'innowave2k26');
