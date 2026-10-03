<?php

declare(strict_types=1);

namespace Chess\Game;

use Chess\Clock;
use DateTimeImmutable;

/**
 * Server-side time banks. Display times are derived from stored remainings plus
 * elapsed time on the side to move; timeouts are enforced lazily on reads and
 * moves the same way waiting expiry is.
 */
final class MatchClock
{
    public function __construct(private readonly Clock $clock)
    {
    }

    /**
     * @return array{white: int, black: int, running: string|null}
     */
    public function display(GameMatch $match, Color $sideToMove): array
    {
        $full = $match->timeControl->value * 1000;

        if ($match->status === MatchStatus::Finished) {
            return [
                'white' => $match->whiteRemainingMs ?? $full,
                'black' => $match->blackRemainingMs ?? $full,
                'running' => null,
            ];
        }

        if (!$match->status->clocksMayRun()) {
            return ['white' => $full, 'black' => $full, 'running' => null];
        }

        $white = $match->whiteRemainingMs ?? $full;
        $black = $match->blackRemainingMs ?? $full;
        $elapsed = $this->elapsedMs($match->turnStartedAt);

        if ($sideToMove === Color::White) {
            $white = max(0, $white - $elapsed);
        } else {
            $black = max(0, $black - $elapsed);
        }

        return [
            'white' => $white,
            'black' => $black,
            'running' => $sideToMove->value,
        ];
    }

    /** The side that has no time left on their turn, if any. */
    public function flaggedSide(GameMatch $match, Color $sideToMove): ?Color
    {
        if ($match->status !== MatchStatus::Active || $match->turnStartedAt === null) {
            return null;
        }

        $display = $this->display($match, $sideToMove);
        $remaining = $display[$sideToMove->value];

        return $remaining <= 0 ? $sideToMove : null;
    }

    public function finishOnTimeout(GameMatch $match, Color $loser): GameMatch
    {
        $winner = $loser->opposite();
        $display = $this->display($match, $loser);

        return $match->withFinishedOnTimeout(
            whiteRemainingMs: $display['white'],
            blackRemainingMs: $display['black'],
            winner: $winner,
        );
    }

    /** Updates banks after a ply and starts the clock on white's first move. */
    public function afterAcceptedMove(GameMatch $match, Color $mover, Position $after): GameMatch
    {
        $now = $this->clock->now();
        $full = $match->timeControl->value * 1000;
        $starting = $match->status === MatchStatus::Ready;

        $white = $match->whiteRemainingMs ?? $full;
        $black = $match->blackRemainingMs ?? $full;
        $turnStartedAt = $starting ? $now : $match->turnStartedAt ?? $now;

        if (!$starting) {
            $elapsed = $this->elapsedMs($turnStartedAt, $now);
            if ($mover === Color::White) {
                $white = max(0, $white - $elapsed);
            } else {
                $black = max(0, $black - $elapsed);
            }
        }

        return $match->withMovePlayed($after)->withClock(
            whiteRemainingMs: $white,
            blackRemainingMs: $black,
            turnStartedAt: $now,
        );
    }

    private function elapsedMs(?DateTimeImmutable $since, ?DateTimeImmutable $until = null): int
    {
        if ($since === null) {
            return 0;
        }

        $until ??= $this->clock->now();
        $seconds = $until->getTimestamp() - $since->getTimestamp();
        $fraction = $until->format('v') - $since->format('v');

        return max(0, ($seconds * 1000) + $fraction);
    }
}
