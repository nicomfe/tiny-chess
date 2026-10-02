<?php

declare(strict_types=1);

namespace Chess\Game;

use Chess\Security\Tokens;

/**
 * Decides who a visitor is for a given match, and seats the opponent.
 *
 * Precedence is creator token, then joiner token, then nobody: the creator link
 * is the one credential that survives a lost cookie, so it must never be
 * overridden — and a creator following their own link never takes or gives up
 * the joiner seat.
 */
final class Seating
{
    public function __construct(
        private readonly MatchRepository $matches,
        private readonly Tokens $tokens,
    ) {
    }

    /** Reads the visitor's role without changing the match. */
    public function resolve(GameMatch $match, ?string $creatorToken, ?string $joinerToken): Seat
    {
        return Seat::existing($match, $this->roleFor($match, $creatorToken, $joinerToken));
    }

    /**
     * As `resolve`, but the first visitor who is nobody yet takes the open
     * joiner seat. Only the play page claims, so that a background poll — or a
     * creator whose request lost its token — cannot seat someone by accident.
     */
    public function claim(GameMatch $match, ?string $creatorToken, ?string $joinerToken): Seat
    {
        $role = $this->roleFor($match, $creatorToken, $joinerToken);
        if ($role !== Role::Spectator || !$this->seatIsOpen($match)) {
            return Seat::existing($match, $role);
        }

        $issuedToken = $this->tokens->issue();
        $seated = $match->withJoiner($this->tokens->hash($issuedToken));

        if (!$this->matches->claimJoiner($seated)) {
            // Someone else was seated between the read and the write, so re-read
            // rather than reporting the `waiting` we set out with.
            return Seat::existing($this->matches->find($match->id) ?? $match, Role::Spectator);
        }

        return Seat::justClaimed($seated, $issuedToken);
    }

    private function roleFor(GameMatch $match, ?string $creatorToken, ?string $joinerToken): Role
    {
        if ($creatorToken !== null && $this->tokens->verify($creatorToken, $match->creatorTokenHash)) {
            return Role::Creator;
        }

        if ($joinerToken !== null
            && $match->joinerTokenHash !== null
            && $this->tokens->verify($joinerToken, $match->joinerTokenHash)) {
            return Role::Joiner;
        }

        return Role::Spectator;
    }

    private function seatIsOpen(GameMatch $match): bool
    {
        return !$match->hasJoiner() && $match->status === MatchStatus::Waiting;
    }
}
