-- COLLECT & CONNECT – vereinheitlichtes Datenmodell
-- Eine Tabelle fuer alle Fragetypen. Der Typ steht in form_type,
-- die typ-spezifischen Felder liegen als JSON in payload.
--
-- Hinweis: MySQL 5.7+/MariaDB 10.2+ unterstuetzen den JSON-Typ.
-- Bei aelteren Servern payload einfach als LONGTEXT anlegen.

CREATE TABLE IF NOT EXISTS submissions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    form_type  VARCHAR(32)  NOT NULL,      -- 'feedback' | 'exit_ticket' | 'strukturiert' | ...
    klasse     VARCHAR(50)  NOT NULL,
    nickname   VARCHAR(100) NULL,          -- optional
    payload    JSON         NOT NULL,      -- typ-spezifische Felder
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_form_type (form_type),
    INDEX idx_klasse (klasse),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Das alte Freitext-System (Tabelle `memoranda`) und die Zwischenloesung
-- `memoranda_structured` werden nicht mehr verwendet. Wenn du sie loeschen willst:
-- DROP TABLE IF EXISTS memoranda;
-- DROP TABLE IF EXISTS memoranda_structured;
