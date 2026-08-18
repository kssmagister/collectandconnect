-- Bericht-Link zurueck von der DRP: wenn der Ubuntu-Server eine angeforderte
-- Auswertung erledigt, meldet er (optional) die URL des fertigen Berichts
-- zurueck, statt dass die "angefordert"-Markierung nur spurlos verschwindet.
-- Additiv -> vor dem Deploy ausfuehrbar.

ALTER TABLE lessons ADD COLUMN last_report_url VARCHAR(500) NULL;
ALTER TABLE lessons ADD COLUMN last_report_at  TIMESTAMP    NULL;
