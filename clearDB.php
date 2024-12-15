<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$sql = "DELETE FROM memoranda";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'Database cleared successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error clearing database: ' . $conn->error]);
}

$conn->close();
?>
