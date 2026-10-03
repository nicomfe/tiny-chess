ALTER TABLE matches
    ADD COLUMN fen VARCHAR(100) NOT NULL
        DEFAULT 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'
        AFTER status;
