-- UCI is the source of truth; `san` is a cached rendering for the move list, so
-- serving state never has to replay the game.
--
-- Racing submissions are turned away by the row lock the app takes on the match;
-- the primary key is what makes that a guarantee rather than a convention, since
-- two requests computing the same move number cannot both own it.
CREATE TABLE moves (
    match_id CHAR(32) NOT NULL,
    move_number SMALLINT UNSIGNED NOT NULL,
    uci VARCHAR(5) NOT NULL,
    san VARCHAR(10) NOT NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (match_id, move_number),
    CONSTRAINT moves_match FOREIGN KEY (match_id) REFERENCES matches (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
