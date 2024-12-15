<?php
session_start();
require_once 'config.php';

$adminUsername = "admin";
$adminPassword = "Theoderich007!";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($username === $adminUsername && $password === $adminPassword) {
    $_SESSION['loggedin'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}
?>
