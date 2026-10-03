<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * A match and its plies as of one instant.
 *
 * The two travel together because a response built from a position and a move
 * list read moments apart can disagree with itself: a board one ply behind its
 * own move list, and a player told to wait on a turn that is already theirs.
 */
final class MatchSnapshot
{
    public function __construct(
        public readonly GameMatch $match,
        public readonly MoveLog $moves,
    ) {
    }
}
