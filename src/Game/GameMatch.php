<?php

declare(strict_types=1);

namespace Chess\Game;

use DateTimeImmutable;
use DateTimeZone;

/** A persisted challenge/match row. */
final class GameMatch
{
    public function __construct(
        public readonly string $id,
        public readonly MatchStatus $status,
        public readonly TimeControl $timeControl,
        public readonly Color $creatorColor,
        public readonly string $creatorTokenHash,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            status: MatchStatus::from((string) $row['status']),
            timeControl: TimeControl::from((int) $row['time_control_seconds']),
            creatorColor: Color::from((string) $row['creator_color']),
            creatorTokenHash: (string) $row['creator_token_hash'],
            createdAt: new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
        );
    }

    public function creatorPlaysWhite(): bool
    {
        return $this->creatorColor === Color::White;
    }

    public function joinerColor(): Color
    {
        return $this->creatorColor->opposite();
    }
}
