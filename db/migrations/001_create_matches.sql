CREATE TABLE matches (
    id CHAR(32) NOT NULL,
    status VARCHAR(16) NOT NULL,
    time_control_seconds SMALLINT UNSIGNED NOT NULL,
    creator_color ENUM('white', 'black') NOT NULL,
    creator_token_hash CHAR(64) NOT NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    KEY status_created_at (status, created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
