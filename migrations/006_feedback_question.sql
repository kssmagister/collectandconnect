-- Feedback-Frage je Lektion: Die Lehrperson kann eine konkrete Frage formulieren,
-- die den Schuelern im Feedback-Formular angezeigt wird. Additiv -> vor dem Deploy
-- ausfuehrbar.

ALTER TABLE lessons ADD COLUMN feedback_question VARCHAR(500) NULL;
