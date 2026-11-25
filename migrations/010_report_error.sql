-- Fehlerrueckmeldung von der DRP: schlaegt eine angeforderte Auswertung fehl
-- (z.B. Zeitueberschreitung beim KI-Modell), meldet der Ubuntu-Server
-- action=failed mit Grund zurueck. lessons.php zeigt dann "fehlgeschlagen"
-- statt dass die Markierung spurlos verschwindet.
-- Additiv -> vor dem Deploy ausfuehrbar. (Urspruenglich als 009 angelegt, wegen
-- 009_lesson_hidden.sql auf 010 umnummeriert.)

ALTER TABLE lessons ADD COLUMN last_error    VARCHAR(500) NULL;
ALTER TABLE lessons ADD COLUMN last_error_at TIMESTAMP    NULL;
