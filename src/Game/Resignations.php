<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * A seated player concedes. The opponent wins; the position is left as it was.
 */
final class Resignations
{
    public function __construct(
        private readonly MatchRepository $matches,
        private readonly MatchClock $clock,
        private readonly MatchTiming $timing,
    ) {
    }

    /**
     * @throws ActionRejected with the reason to show the caller
     */
    public function resign(GameMatch $match, Role $role): MatchSnapshot
    {
        $color = $match->colorFor($role);
        if ($color === null) {
            throw ActionRejected::because(ActionRejection::NotAPlayer);
        }

        // Timeouts commit in their own transaction so a resignation cannot
        // undo a flag that already happened.
        $current = $this->matches->snapshot(
            $match->id,
            fn (MatchSnapshot $snapshot): MatchSnapshot => $this->timing->refresh($snapshot),
        ) ?? throw ActionRejected::because(ActionRejection::MatchNotFound);

        if (!$current->match->allowsMoves()) {
            throw ActionRejected::because($current->match->status === MatchStatus::Finished
                ? ActionRejection::MatchFinished
                : ActionRejection::MatchNotStarted);
        }

        return $this->matches->transactionally(
            fn (): MatchSnapshot => $this->commit($match->id, $color),
        );
    }

    /** Runs with the match row locked, so two resignations cannot both land. */
    private function commit(string $matchId, Color $color): MatchSnapshot
    {
        $match = $this->matches->findForUpdate($matchId)
            ?? throw ActionRejected::because(ActionRejection::MatchNotFound);

        if (!$match->allowsMoves()) {
            throw ActionRejected::because($match->status === MatchStatus::Finished
                ? ActionRejection::MatchFinished
                : ActionRejection::MatchNotStarted);
        }

        $display = $this->clock->display($match, $match->position()->sideToMove());
        $finished = $match->withFinished(
            whiteRemainingMs: $display['white'],
            blackRemainingMs: $display['black'],
            winner: $color->opposite(),
            reason: GameResultReason::Resign,
        );
        $this->matches->saveClockState($finished);

        return new MatchSnapshot($finished, $this->matches->movesFor($finished->id));
    }
}
