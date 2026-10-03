<?php

declare(strict_types=1);

namespace Chess\Game;

/** A position that can no longer be played, and why. */
final readonly class GameTermination
{
    public function __construct(
        public ?Color $winner,
        public GameResultReason $reason,
    ) {
    }
}
