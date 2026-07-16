-- Persoenliche Klassenauswahl je Lehrperson.
-- JSON-Array der Klassennamen; NULL/leer = alle Klassen anzeigen (wie bisher).
-- Additiv -> vor dem Deploy ausfuehrbar.

ALTER TABLE teachers ADD COLUMN classes TEXT NULL;
