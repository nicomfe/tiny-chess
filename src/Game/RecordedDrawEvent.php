<?php

declare(strict_types=1);

namespace Chess\Game;

/** One stored draw negotiation step for the move list and replay. */
final class RecordedDrawEvent
{
    public function __construct(
        public readonly int $number,
        public readonly DrawEventKind $kind,
        public readonly Color $by,
        public readonly int $afterMove,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            number: (int) $row['event_number'],
            kind: DrawEventKind::from((string) $row['kind']),
            by: Color::from((string) $row['by_color']),
            afterMove: (int) $row['after_move_number'],
        );
    }
}
