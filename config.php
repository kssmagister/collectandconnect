<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function loadEnv() {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        die('.env file not found');
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Set session cookie parameters for better security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Live laeuft ueber HTTPS

// Load environment variables
loadEnv();

// Database configuration
$servername = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

// Admin credentials
$adminUsername = getenv('ADMIN_USERNAME');
$adminPassword = getenv('ADMIN_PASSWORD');

// Verify all required environment variables are set
$required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ADMIN_USERNAME', 'ADMIN_PASSWORD'];
foreach ($required_vars as $var) {
    if (!getenv($var)) {
        die("Environment variable $var is not set");
    }
}

// Fehler protokollieren statt an den Client ausgeben (kein Info-Leak in Produktion)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Hinweis: Die eigentliche DB-Verbindung wird pro Endpunkt bei Bedarf aufgebaut.
// Frueher wurde hier zusaetzlich bei JEDEM Include eine Test-Verbindung geoeffnet
// (und nie geschlossen) - das war unnoetiger Overhead und wurde entfernt.
