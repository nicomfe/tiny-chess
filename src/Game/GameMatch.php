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
        public readonly ?string $joinerTokenHash = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $joinerTokenHash = $row['joiner_token_hash'] ?? null;

        return new self(
            id: (string) $row['id'],
            status: MatchStatus::from((string) $row['status']),
            timeControl: TimeControl::from((int) $row['time_control_seconds']),
            creatorColor: Color::from((string) $row['creator_color']),
            creatorTokenHash: (string) $row['creator_token_hash'],
            createdAt: new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
            joinerTokenHash: $joinerTokenHash === null ? null : (string) $joinerTokenHash,
        );
    }

    public function hasJoiner(): bool
    {
        return $this->joinerTokenHash !== null;
    }

    /** The state after the joiner seat is taken: clocks stay idle until white moves. */
    public function withJoiner(string $joinerTokenHash): self
    {
        return new self(
            id: $this->id,
            status: MatchStatus::Ready,
            timeControl: $this->timeControl,
            creatorColor: $this->creatorColor,
            creatorTokenHash: $this->creatorTokenHash,
            createdAt: $this->createdAt,
            joinerTokenHash: $joinerTokenHash,
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
