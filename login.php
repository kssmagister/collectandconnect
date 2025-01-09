<?php
session_start();
require_once 'config.php';

$adminUsername = "xxx";
$adminPassword = "xxx";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($username === $adminUsername && $password === $adminPassword) {
    $_SESSION['loggedin'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}
?>
