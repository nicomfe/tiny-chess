ALTER TABLE matches
    ADD COLUMN joiner_token_hash CHAR(64) NULL AFTER creator_token_hash;
