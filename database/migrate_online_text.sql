-- Add assignment submission modes and rich-text answer format.
-- Run once on an existing database; existing assignments retain the current
-- file-and-optional-note behavior through the default 'both' mode.
USE elearning;

ALTER TABLE assignments
  ADD COLUMN submission_mode ENUM('file','online_text','both') NOT NULL DEFAULT 'both'
  AFTER max_score;

ALTER TABLE submissions
  ADD COLUMN answer_format ENUM('plain','html') NOT NULL DEFAULT 'plain'
  AFTER answer_text;
