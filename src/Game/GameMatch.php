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
        public readonly ?int $whiteRemainingMs = null,
        public readonly ?int $blackRemainingMs = null,
        public readonly ?DateTimeImmutable $turnStartedAt = null,
        public readonly ?Color $resultWinner = null,
        public readonly ?GameResultReason $resultReason = null,
        public readonly ?Color $drawOfferBy = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $joinerTokenHash = $row['joiner_token_hash'] ?? null;
        $turnStartedAt = $row['turn_started_at'] ?? null;
        $resultWinner = $row['result_winner_color'] ?? null;
        $resultReason = $row['result_reason'] ?? null;
        $drawOfferBy = $row['draw_offer_color'] ?? null;

        return new self(
            id: (string) $row['id'],
            status: MatchStatus::from((string) $row['status']),
            fen: (string) $row['fen'],
            timeControl: TimeControl::from((int) $row['time_control_seconds']),
            creatorColor: Color::from((string) $row['creator_color']),
            creatorTokenHash: (string) $row['creator_token_hash'],
            createdAt: new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
            joinerTokenHash: $joinerTokenHash === null ? null : (string) $joinerTokenHash,
            whiteRemainingMs: ($row['white_remaining_ms'] ?? null) !== null ? (int) $row['white_remaining_ms'] : null,
            blackRemainingMs: ($row['black_remaining_ms'] ?? null) !== null ? (int) $row['black_remaining_ms'] : null,
            turnStartedAt: $turnStartedAt === null
                ? null
                : new DateTimeImmutable((string) $turnStartedAt, new DateTimeZone('UTC')),
            resultWinner: $resultWinner === null ? null : Color::from((string) $resultWinner),
            resultReason: $resultReason === null ? null : GameResultReason::from((string) $resultReason),
            drawOfferBy: $drawOfferBy === null ? null : Color::from((string) $drawOfferBy),
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
        return $this->with(status: MatchStatus::Active, fen: $after->fen, clearDrawOffer: true);
    }

    public function withClock(
        int $whiteRemainingMs,
        int $blackRemainingMs,
        DateTimeImmutable $turnStartedAt,
    ): self {
        return $this->with(
            whiteRemainingMs: $whiteRemainingMs,
            blackRemainingMs: $blackRemainingMs,
            turnStartedAt: $turnStartedAt,
        );
    }

    public function withFinishedOnTimeout(
        int $whiteRemainingMs,
        int $blackRemainingMs,
        Color $winner,
    ): self {
        return $this->withFinished(
            whiteRemainingMs: $whiteRemainingMs,
            blackRemainingMs: $blackRemainingMs,
            winner: $winner,
            reason: GameResultReason::Timeout,
        );
    }

    public function withFinished(
        int $whiteRemainingMs,
        int $blackRemainingMs,
        ?Color $winner,
        GameResultReason $reason,
    ): self {
        return $this->with(
            status: MatchStatus::Finished,
            whiteRemainingMs: $whiteRemainingMs,
            blackRemainingMs: $blackRemainingMs,
            resultWinner: $winner,
            resultReason: $reason,
            clearTurnStartedAt: true,
            clearDrawOffer: true,
        );
    }

    /** A short result line for finished games, or empty while play continues. */
    public function resultHeadline(): string
    {
        if ($this->status !== MatchStatus::Finished || $this->resultReason === null) {
            return '';
        }

        if ($this->resultWinner === null) {
            return match ($this->resultReason) {
                GameResultReason::Stalemate => 'Draw by stalemate',
                GameResultReason::InsufficientMaterial => 'Draw by insufficient material',
                GameResultReason::Agreement => 'Draw by agreement',
                default => 'Draw',
            };
        }

        $side = ucfirst($this->resultWinner->value);

        return match ($this->resultReason) {
            GameResultReason::Checkmate => "{$side} wins by checkmate",
            GameResultReason::Timeout => "{$side} wins on time",
            GameResultReason::Resign => "{$side} wins by resignation",
            default => "{$side} wins",
        };
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

    public function withDrawOffered(Color $by): self
    {
        return $this->with(drawOfferBy: $by);
    }

    public function withoutDrawOffer(): self
    {
        return $this->with(clearDrawOffer: true);
    }

    private function with(
        ?MatchStatus $status = null,
        ?string $fen = null,
        ?string $joinerTokenHash = null,
        ?int $whiteRemainingMs = null,
        ?int $blackRemainingMs = null,
        ?DateTimeImmutable $turnStartedAt = null,
        ?Color $resultWinner = null,
        ?GameResultReason $resultReason = null,
        ?Color $drawOfferBy = null,
        bool $clearTurnStartedAt = false,
        bool $clearDrawOffer = false,
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
            whiteRemainingMs: $whiteRemainingMs ?? $this->whiteRemainingMs,
            blackRemainingMs: $blackRemainingMs ?? $this->blackRemainingMs,
            turnStartedAt: $clearTurnStartedAt ? null : ($turnStartedAt ?? $this->turnStartedAt),
            resultWinner: $resultWinner ?? $this->resultWinner,
            resultReason: $resultReason ?? $this->resultReason,
            drawOfferBy: $clearDrawOffer ? null : ($drawOfferBy ?? $this->drawOfferBy),
        );
    }
}
