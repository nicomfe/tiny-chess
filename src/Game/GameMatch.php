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
        public readonly string $fen,
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
            fen: (string) $row['fen'],
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
        return $this->with(status: MatchStatus::Ready, joinerTokenHash: $joinerTokenHash);
    }

    /**
     * The state after a ply lands. Reaching here from `ready` is white's first
     * move, which is exactly what starts the game.
     */
    public function withMovePlayed(Position $after): self
    {
        return $this->with(status: MatchStatus::Active, fen: $after->fen);
    }

    public function position(): Position
    {
        return Position::fromFen($this->fen);
    }

    public function creatorPlaysWhite(): bool
    {
        return $this->creatorColor === Color::White;
    }

    public function joinerColor(): Color
    {
        return $this->creatorColor->opposite();
    }

    /** The color a role plays, or null for anyone who is only watching. */
    public function colorFor(Role $role): ?Color
    {
        return match ($role) {
            Role::Creator => $this->creatorColor,
            Role::Joiner => $this->joinerColor(),
            Role::Spectator => null,
        };
    }

    /** Both seats are taken and the game has not ended, so a ply may land. */
    public function allowsMoves(): bool
    {
        return $this->status === MatchStatus::Ready || $this->status === MatchStatus::Active;
    }

    private function with(
        ?MatchStatus $status = null,
        ?string $fen = null,
        ?string $joinerTokenHash = null,
    ): self {
        return new self(
            id: $this->id,
            status: $status ?? $this->status,
            fen: $fen ?? $this->fen,
            timeControl: $this->timeControl,
            creatorColor: $this->creatorColor,
            creatorTokenHash: $this->creatorTokenHash,
            createdAt: $this->createdAt,
            joinerTokenHash: $joinerTokenHash ?? $this->joinerTokenHash,
        );
    }
}
