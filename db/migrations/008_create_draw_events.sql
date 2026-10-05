CREATE TABLE draw_events (
    match_id CHAR(32) NOT NULL,
    event_number SMALLINT UNSIGNED NOT NULL,
    kind ENUM('offer', 'accept', 'decline') NOT NULL,
    by_color ENUM('white', 'black') NOT NULL,
    after_move_number SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (match_id, event_number),
    CONSTRAINT draw_events_match FOREIGN KEY (match_id) REFERENCES matches (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
