<?php
require_once 'config.php'; // startet Session + laedt .env ($adminUsername/$adminPassword)

header('Content-Type: application/json');

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Zugangsdaten kommen ausschliesslich aus der .env (config.php), niemals hardcodiert.
// hash_equals: konstante Laufzeit gegen Timing-Angriffe.
if (hash_equals($adminUsername, $username) && hash_equals($adminPassword, $password)) {
    session_regenerate_id(true); // Session-Fixation verhindern
    $_SESSION['loggedin'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}
