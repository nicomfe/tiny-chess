<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * Match lifecycle:
 *   waiting   → no opponent yet; clocks idle, no moves
 *   abandoned → still waiting past the one-hour expiry
 *   ready     → opponent seated; clocks idle until white's first move
 *   active    → clocks running
 *   finished  → terminal; read-only replay
 */
enum MatchStatus: string
{
    case Waiting = 'waiting';
    case Abandoned = 'abandoned';
    case Ready = 'ready';
    case Active = 'active';
    case Finished = 'finished';

    /** True while stored banks and `turn_started_at` drive a live countdown. */
    public function clocksMayRun(): bool
    {
        return $this === self::Active;
    }
}
