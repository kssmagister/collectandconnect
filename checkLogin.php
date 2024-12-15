<?php
session_start();

$response = ['loggedIn' => false];

if (isset($_SESSION['loggedIn']) && $_SESSION['loggedIn']) {
    $response['loggedIn'] = true;
}

echo json_encode($response);
?>
