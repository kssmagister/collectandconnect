<?php
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

// Add error reporting for database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test database connection
function testDatabaseConnection() {
    global $servername, $username, $password, $dbname;
    
    $conn = @new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error . 
            "\nServer: " . $servername .
            "\nDatabase: " . $dbname .
            "\nUser: " . $username);
    }
    return $conn;
}

// Test the connection when config is loaded
testDatabaseConnection();
