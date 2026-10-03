ALTER TABLE matches
    ADD COLUMN draw_offer_color ENUM('white', 'black') NULL AFTER result_reason;
