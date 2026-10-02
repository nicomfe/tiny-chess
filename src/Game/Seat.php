<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * Who a visitor is for one match, and the match as it stands afterwards —
 * claiming the joiner seat moves the match to `ready`, so the two travel
 * together.
 */
final class Seat
{
    private function __construct(
        public readonly GameMatch $match,
        public readonly Role $role,
        /** Set only when this request took the seat; the caller must hand it to the browser. */
        public readonly ?string $issuedJoinerToken,
    ) {
    }

    public static function existing(GameMatch $match, Role $role): self
    {
        return new self($match, $role, null);
    }

    public static function justClaimed(GameMatch $match, string $joinerToken): self
    {
        return new self($match, Role::Joiner, $joinerToken);
    }
}
