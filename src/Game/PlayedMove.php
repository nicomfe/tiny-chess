<?php

declare(strict_types=1);

namespace Chess\Game;

/** A move that turned out to be legal, with the position it produced. */
final class PlayedMove
{
    public function __construct(
        public readonly UciMove $move,
        public readonly string $san,
        public readonly Position $after,
    ) {
    }
}
