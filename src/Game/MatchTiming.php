<?php

declare(strict_types=1);

namespace Chess\Game;

use Chess\Clock;

/**
 * Applies clock rules and waiting expiry on reads and after plies. Timeouts
 * and abandoned challenges are persisted when detected so every client sees
 * the same result.
 */
final class MatchTiming
{
    public function __construct(
        private readonly MatchRepository $matches,
        private readonly MatchClock $clock,
        private readonly Clock $time,
    ) {
    }

    public function refresh(MatchSnapshot $snapshot): MatchSnapshot
    {
        $current = $this->applyTimeoutIfNeeded($this->expireWaiting($snapshot->match));

        return $current === $snapshot->match ? $snapshot : new MatchSnapshot($current, $snapshot->moves);
    }

    /** Page load and other unlocked reads only expire waiting challenges. */
    public function expireWaiting(GameMatch $match): GameMatch
    {
        if (!$match->waitingHasExpired($this->time->now())) {
            return $match;
        }

        if ($this->matches->abandonIfStillWaiting($match->id)) {
            return $match->withAbandoned();
        }

        return $this->matches->find($match->id) ?? $match;
    }

    public function afterMove(GameMatch $match, Color $mover, Position $after): GameMatch
    {
        $updated = $this->clock->afterAcceptedMove($match, $mover, $after);
        $termination = $after->termination();

        if ($termination === null) {
            return $updated;
        }

        return $updated->withFinished(
            whiteRemainingMs: $updated->whiteRemainingMs ?? $match->timeControl->value * 1000,
            blackRemainingMs: $updated->blackRemainingMs ?? $match->timeControl->value * 1000,
            winner: $termination->winner,
            reason: $termination->reason,
        );
    }

    private function applyTimeoutIfNeeded(GameMatch $match): GameMatch
    {
        if ($match->status !== MatchStatus::Active) {
            return $match;
        }

        $sideToMove = $match->position()->sideToMove();
        $flagged = $this->clock->flaggedSide($match, $sideToMove);
        if ($flagged === null) {
            return $match;
        }

        $finished = $this->clock->finishOnTimeout($match, $flagged);
        $this->matches->saveClockState($finished);

        return $finished;
    }
}
