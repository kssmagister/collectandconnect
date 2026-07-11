-- Web-Trigger: Auswertung einer Lektion aus der Oberflaeche anfordern.
-- Der Ubuntu-Server pollt diese Markierung (api_analysis_queue.php) und
-- erzeugt den Bericht. Additiv -> vor dem Deploy ausfuehrbar.

ALTER TABLE lessons ADD COLUMN analysis_requested    TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE lessons ADD COLUMN analysis_requested_at TIMESTAMP  NULL;
