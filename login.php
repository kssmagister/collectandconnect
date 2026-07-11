<?php
// Admin-/Lehrer-Login: Pruefung gegen die teachers-Tabelle (bcrypt) + Rate-Limit.
// Rate-Limit ist "fail-open": geht die Zaehl-Tabelle nicht, wird die Bremse
// uebersprungen, damit der Login nie ganz blockiert.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$inUser = $_POST['username'] ?? '';
$inPass = $_POST['password'] ?? '';

// Echte Client-IP bevorzugen (hinter Proxy waere REMOTE_ADDR fuer alle gleich).
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', $ip)[0]);

$conn = db();

$maxAttempts = 8;
$windowMin   = 15;

try { $conn->query(
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_ip_time (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
); } catch (\Throwable $e) {}

// ── Rate-Limit ─────────────────────────────────────────────────────────
$fails = 0; $throttleOk = false;
try {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM login_attempts
         WHERE ip = ? AND created_at > (NOW() - INTERVAL $windowMin MINUTE)"
    );
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $fails = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    $throttleOk = true;
} catch (\Throwable $e) { $throttleOk = false; }

if ($throttleOk && $fails >= $maxAttempts) {
    http_response_code(429);
    echo json_encode(['success' => false,
        'message' => 'Zu viele Fehlversuche. Bitte in etwa ' . $windowMin . ' Minuten erneut versuchen.']);
    exit;
}

// ── Zugangsdaten gegen teachers-Tabelle pruefen ────────────────────────
$teacher = null;
try {
    $stmt = $conn->prepare(
        "SELECT id, password_hash, name, code, is_admin FROM teachers WHERE username = ? LIMIT 1"
    );
    $stmt->bind_param('s', $inUser);
    $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (\Throwable $e) {
    // Tabelle fehlt vermutlich -> Setup unvollstaendig
    echo json_encode(['success' => false,
        'message' => 'Setup unvollstaendig: bitte Migration 003_multiteacher.sql ausfuehren.']);
    exit;
}

$passOk = $teacher && password_verify($inPass, $teacher['password_hash']);

if (isset($_GET['dbg'])) { // TEMP-DIAGNOSE
    echo json_encode(['dbg' => [
        'found'    => (bool) $teacher,
        'hashlen'  => $teacher ? strlen($teacher['password_hash']) : 0,
        'hashpre'  => $teacher ? substr($teacher['password_hash'], 0, 7) : '',
        'verify'   => $passOk,
    ]]);
    exit;
}

if ($passOk) {
    if ($throttleOk) {
        try { $del = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
            $del->bind_param('s', $ip); $del->execute(); $del->close(); } catch (\Throwable $e) {}
    }
    session_regenerate_id(true);
    $_SESSION['loggedin']     = true;
    $_SESSION['teacher_id']   = (int) $teacher['id'];
    $_SESSION['teacher_name'] = $teacher['name'];
    $_SESSION['teacher_code'] = $teacher['code'];
    $_SESSION['is_admin']     = (int) $teacher['is_admin'] === 1;
    csrf_token();
    echo json_encode(['success' => true]);
} else {
    if ($throttleOk) {
        try {
            $ins = $conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
            $ins->bind_param('s', $ip); $ins->execute(); $ins->close();
            $conn->query("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
        } catch (\Throwable $e) {}
    }
    usleep(300000);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}

$conn->close();
