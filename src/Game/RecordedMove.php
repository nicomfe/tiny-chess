<?php

declare(strict_types=1);

namespace Chess\Game;

/** One stored ply: its 1-based number, the UCI that is kept, and SAN to show. */
final class RecordedMove
{
    public function __construct(
        public readonly int $number,
        public readonly string $uci,
        public readonly string $san,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            number: (int) $row['move_number'],
            uci: (string) $row['uci'],
            san: (string) $row['san'],
        );
    }
}
