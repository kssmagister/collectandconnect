<?php
// Konten-Aktionen (nur Admin, CSRF-geschuetzt): add / edit / resetpw / delete.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_admin();
require_csrf();

$conn = db();
$action = $_POST['action'] ?? '';
$myId = current_teacher_id();

function gen_code(mysqli $conn): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // ohne 0/O/1/I
    do {
        $code = '';
        for ($i = 0; $i < 5; $i++) { $code .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
        $s = $conn->prepare("SELECT 1 FROM teachers WHERE code = ?");
        $s->bind_param('s', $code); $s->execute();
        $exists = (bool) $s->get_result()->fetch_row();
        $s->close();
    } while ($exists);
    return $code;
}

if ($action === 'add') {
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $code     = strtoupper(trim($_POST['code'] ?? ''));
    if ($name === '' || $username === '' || strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Name, Benutzername und Passwort (min. 6 Zeichen) noetig.']); exit;
    }
    if ($code === '') { $code = gen_code($conn); }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $conn->prepare("INSERT INTO teachers (username, password_hash, name, code, is_admin) VALUES (?,?,?,?,0)");
        $stmt->bind_param('ssss', $username, $hash, $name, $code);
        $stmt->execute(); $stmt->close();
        echo json_encode(['success' => true, 'code' => $code]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Benutzername oder Code bereits vergeben.']);
    }
    exit;
}

if ($action === 'edit') {
    $id   = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($id <= 0 || $name === '' || $code === '') {
        echo json_encode(['success' => false, 'message' => 'Name und Code noetig.']); exit;
    }
    try {
        $stmt = $conn->prepare("UPDATE teachers SET name = ?, code = ? WHERE id = ?");
        $stmt->bind_param('ssi', $name, $code, $id);
        $stmt->execute(); $stmt->close();
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Code bereits vergeben.']);
    }
    exit;
}

if ($action === 'resetpw') {
    $id       = (int) ($_POST['id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');
    if ($id <= 0 || strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Passwort min. 6 Zeichen.']); exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE teachers SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $id);
    $stmt->execute(); $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === $myId) {
        echo json_encode(['success' => false, 'message' => 'Das eigene Konto kann nicht geloescht werden.']); exit;
    }
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Ungueltig.']); exit; }
    $conn->begin_transaction();
    try {
        $s1 = $conn->prepare("DELETE FROM submissions WHERE teacher_id = ?");
        $s1->bind_param('i', $id); $s1->execute(); $s1->close();
        $s2 = $conn->prepare("DELETE FROM teachers WHERE id = ?");
        $s2->bind_param('i', $id); $s2->execute(); $s2->close();
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Fehler beim Loeschen.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion.']);
