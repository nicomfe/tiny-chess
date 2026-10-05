<?php

declare(strict_types=1);

namespace Chess\Game;

use Chess\Clock;

/** Offer, accept, or decline a draw while a match is still being played. */
final class Draws
{
    public function __construct(
        private readonly MatchRepository $matches,
        private readonly MatchClock $clock,
        private readonly MatchTiming $timing,
        private readonly Clock $wallClock,
    ) {
    }

    /**
     * @throws ActionRejected with the reason to show the caller
     */
    public function offer(GameMatch $match, Role $role): MatchSnapshot
    {
        return $this->change($match, $role, $this->commitOffer(...));
    }

    /**
     * @throws ActionRejected with the reason to show the caller
     */
    public function accept(GameMatch $match, Role $role): MatchSnapshot
    {
        return $this->change($match, $role, fn (string $id, Color $color): MatchSnapshot => $this->commitAnswer($id, $color, accept: true));
    }

    /**
     * @throws ActionRejected with the reason to show the caller
     */
    public function decline(GameMatch $match, Role $role): MatchSnapshot
    {
        return $this->change($match, $role, fn (string $id, Color $color): MatchSnapshot => $this->commitAnswer($id, $color, accept: false));
    }

    /**
     * @param callable(string, Color): MatchSnapshot $commit
     */
    private function change(GameMatch $match, Role $role, callable $commit): MatchSnapshot
    {
        $color = $match->colorFor($role);
        if ($color === null) {
            throw ActionRejected::because(ActionRejection::NotAPlayer);
        }

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
            fn (): MatchSnapshot => $commit($match->id, $color),
        );
    }

    private function commitOffer(string $matchId, Color $color): MatchSnapshot
    {
        $match = $this->lockedInProgress($matchId);

        if ($match->drawOfferBy !== null && $match->drawOfferBy !== $color) {
            throw ActionRejected::because(ActionRejection::DrawAlreadyOffered);
        }

        $offered = $match->withDrawOffered($color);
        $this->matches->saveDrawOffer($offered);
        $this->recordDrawEvent($matchId, $color, DrawEventKind::Offer);

        return new MatchSnapshot($offered, $this->matches->movesFor($offered->id));
    }

    private function commitAnswer(string $matchId, Color $color, bool $accept): MatchSnapshot
    {
        $match = $this->lockedInProgress($matchId);

        if ($match->drawOfferBy === null) {
            throw ActionRejected::because(ActionRejection::NoDrawOffer);
        }

        if ($match->drawOfferBy === $color) {
            throw ActionRejected::because(ActionRejection::OwnDrawOffer);
        }

        if (!$accept) {
            $declined = $match->withoutDrawOffer();
            $this->matches->saveDrawOffer($declined);
            $this->recordDrawEvent($matchId, $color, DrawEventKind::Decline);

            return new MatchSnapshot($declined, $this->matches->movesFor($declined->id));
        }

        $this->recordDrawEvent($matchId, $color, DrawEventKind::Accept);

        $display = $this->clock->display($match, $match->position()->sideToMove());
        $finished = $match->withFinished(
            whiteRemainingMs: $display['white'],
            blackRemainingMs: $display['black'],
            winner: null,
            reason: GameResultReason::Agreement,
        );
        $this->matches->saveClockState($finished);

        return new MatchSnapshot($finished, $this->matches->movesFor($finished->id));
    }

    private function recordDrawEvent(string $matchId, Color $by, DrawEventKind $kind): void
    {
        $this->matches->appendDrawEvent(
            $matchId,
            $this->matches->lastDrawEventNumber($matchId) + 1,
            $kind,
            $by,
            $this->matches->lastMoveNumber($matchId),
            $this->wallClock->now(),
        );
    }

    private function lockedInProgress(string $matchId): GameMatch
    {
        $match = $this->matches->findForUpdate($matchId)
            ?? throw ActionRejected::because(ActionRejection::MatchNotFound);

        if (!$match->allowsMoves()) {
            throw ActionRejected::because($match->status === MatchStatus::Finished
                ? ActionRejection::MatchFinished
                : ActionRejection::MatchNotStarted);
        }

        return $match;
    }
}
