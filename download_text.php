<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.html');
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "SELECT texteingabe FROM memoranda ORDER BY timestamp DESC";
$result = $conn->query($query);

// Set headers for text file download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="feedback_entries.txt"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output each entry with a dash and new line
while ($row = $result->fetch_assoc()) {
    echo "- " . str_replace("\r\n", "\n", $row['texteingabe']) . "\n\n";
}

$conn->close();
