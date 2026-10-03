-- Automatic draw reasons need more than sixteen characters.
ALTER TABLE matches
    MODIFY COLUMN result_reason VARCHAR(32) NULL;
