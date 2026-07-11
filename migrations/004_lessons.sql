-- Phase 1.5: Lektionen. Jede Lehrperson kann Lektionen anlegen; Antworten
-- koennen einer Lektion zugeordnet werden (Bruecke zur DRP-Lesson).

CREATE TABLE IF NOT EXISTS lessons (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT          NOT NULL,
    code       VARCHAR(12)  NOT NULL UNIQUE,  -- Teil des Links (?l=CODE)
    title      VARCHAR(150) NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Zuordnung der Antwort zu einer Lektion (optional -> NULL erlaubt).
ALTER TABLE submissions ADD COLUMN lesson_id INT NULL AFTER teacher_id;
ALTER TABLE submissions ADD INDEX idx_lesson (lesson_id);
