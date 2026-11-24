-- Lektionen ausblenden: ausgeblendete Lektionen und ihre Antworten erscheinen
-- nicht mehr in Admin, Beamer und DRP-API (sind aber nicht geloescht).
-- Additiv (NOT NULL mit Default) -> vor dem Deploy ausfuehrbar.

ALTER TABLE lessons ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0;
