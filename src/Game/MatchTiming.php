<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * Applies clock rules on reads and after plies. Timeouts are persisted when
 * detected so every client sees the same finished result.
 */
final class MatchTiming
{
    public function __construct(
        private readonly MatchRepository $matches,
        private readonly MatchClock $clock,
    ) {
    }

    public function refresh(MatchSnapshot $snapshot): MatchSnapshot
    {
        $timed = $this->applyTimeoutIfNeeded($snapshot->match);

        return $timed === $snapshot->match ? $snapshot : new MatchSnapshot($timed, $snapshot->moves);
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
