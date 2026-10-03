<?php

declare(strict_types=1);

namespace Chess\Game;

use Chess\Clock;

/**
 * Accepts plies. Every rule the browser could get wrong is checked again here,
 * because the client is not authoritative about legality and is not alone.
 */
final class Moves
{
    public function __construct(
        private readonly MatchRepository $matches,
        private readonly Clock $clock,
        private readonly MatchTiming $timing,
    ) {
    }

    /**
     * The match as it stands after the ply, or nothing at all.
     *
     * @throws MoveRejected with the reason to show the mover
     */
    public function submit(GameMatch $match, Role $role, UciMove $move): MatchSnapshot
    {
        // Which color a role plays is fixed when the challenge is created, so
        // this much can be settled before taking the lock.
        $color = $match->colorFor($role);
        if ($color === null) {
            throw MoveRejected::because(MoveRejection::NotAPlayer);
        }

        // Timeouts commit in their own transaction so a rejected move cannot
        // roll back a flag that already happened.
        $current = $this->matches->snapshot(
            $match->id,
            fn (MatchSnapshot $snapshot): MatchSnapshot => $this->timing->refresh($snapshot),
        ) ?? throw MoveRejected::because(MoveRejection::MatchNotFound);

        if (!$current->match->allowsMoves()) {
            throw MoveRejected::because($current->match->status === MatchStatus::Finished
                ? MoveRejection::MatchFinished
                : MoveRejection::MatchNotStarted);
        }

        return $this->matches->transactionally(
            fn (): MatchSnapshot => $this->append($match->id, $color, $move),
        );
    }

    /** Runs with the match row locked, so nothing it reads can go stale under it. */
    private function append(string $matchId, Color $color, UciMove $move): MatchSnapshot
    {
        $match = $this->matches->findForUpdate($matchId)
            ?? throw MoveRejected::because(MoveRejection::MatchNotFound);

        if (!$match->allowsMoves()) {
            throw MoveRejected::because($match->status === MatchStatus::Finished
                ? MoveRejection::MatchFinished
                : MoveRejection::MatchNotStarted);
        }

        $position = $match->position();
        if ($position->sideToMove() !== $color) {
            throw MoveRejected::because(MoveRejection::NotYourTurn);
        }

        $played = $position->play($move);
        if ($played === null) {
            throw MoveRejected::because(MoveRejection::IllegalMove);
        }

        $this->matches->appendMove(
            $match->id,
            $this->matches->lastMoveNumber($match->id) + 1,
            $played,
            $this->clock->now(),
        );

        $moved = $this->timing->afterMove($match, $color, $played->after);
        $this->matches->updateAfterMove($moved);

        // Read back inside the same transaction, so the mover's own response is
        // as self-consistent as any poll's.
        return new MatchSnapshot($moved, $this->matches->movesFor($moved->id));
    }
}
