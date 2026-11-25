-- Antworttool je Lektion: Die Lehrperson legt beim Erstellen der Lektion fest,
-- welches Formular die Schueler ueber den Lektions-Link sehen
-- (feedback | exit_ticket | strukturiert | aufgabe). NULL = Schueler waehlen
-- selbst auf der Startseite (bisheriges Verhalten).
-- Additiv (NULL-Spalte) -> vor dem Deploy ausfuehrbar.

ALTER TABLE lessons ADD COLUMN form_type VARCHAR(20) NULL;
