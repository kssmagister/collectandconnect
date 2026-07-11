-- Rate-Limit-Tabelle fuer fehlgeschlagene Login-Versuche.
-- Hinweis: login.php legt diese Tabelle bei Bedarf selbst an
-- (CREATE TABLE IF NOT EXISTS). Diese Datei dient nur der Dokumentation
-- bzw. dem manuellen Anlegen.

CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
