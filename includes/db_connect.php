<?php
// includes/db_connect.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Change these for your environment
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'timesheet_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    http_response_code(500);
    echo "Database error.";
    exit;
}

/**
 * Require user to be logged in
 */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /employee-timesheet/index.php');
        exit;
    }
}

/**
 * Require a specific role (e.g. 'admin')
 */
function require_role(string $role): void {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== $role) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}
