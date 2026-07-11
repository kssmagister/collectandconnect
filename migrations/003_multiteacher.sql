-- Mehrbenutzer: Lehrer-Konten + Zuordnung der Antworten zu einer Lehrperson.
-- Wunsch: bestehende Antworten werden verworfen (sauberer Neustart).

-- 1) Lehrer-Konten
CREATE TABLE IF NOT EXISTS teachers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(100) NOT NULL,       -- Anzeigename (sehen die Schueler)
    code          VARCHAR(12)  NOT NULL UNIQUE, -- Teil des persoenlichen Links (?t=CODE)
    is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Admin-Konto anlegen. Login: Benutzer "admin" + Passwort.
--    Den echten bcrypt-Hash NICHT ins oeffentliche Repo committen -> Platzhalter.
--    Passenden INSERT mit echtem Hash separat (z.B. aus dem Chat) ausfuehren.
--    Hash erzeugen: php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"
INSERT INTO teachers (username, password_hash, name, code, is_admin)
VALUES ('admin', 'REPLACE_WITH_BCRYPT_HASH', 'Daniel Rutz', 'RUTZ', 1);

-- 3) Bestehende Antworten verwerfen und teacher_id ergaenzen
DELETE FROM submissions;
ALTER TABLE submissions ADD COLUMN teacher_id INT NOT NULL AFTER id;
ALTER TABLE submissions ADD INDEX idx_teacher (teacher_id);
