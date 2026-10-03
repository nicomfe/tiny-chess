ALTER TABLE matches
    ADD COLUMN white_remaining_ms INT UNSIGNED NULL AFTER fen,
    ADD COLUMN black_remaining_ms INT UNSIGNED NULL AFTER white_remaining_ms,
    ADD COLUMN turn_started_at DATETIME(3) NULL AFTER black_remaining_ms,
    ADD COLUMN result_winner_color ENUM('white', 'black') NULL AFTER turn_started_at,
    ADD COLUMN result_reason VARCHAR(16) NULL AFTER result_winner_color;
