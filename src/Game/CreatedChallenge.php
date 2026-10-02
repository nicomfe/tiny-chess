<?php

declare(strict_types=1);

namespace Chess\Game;

/** A freshly created match plus the raw creator token, which exists only here. */
final class CreatedChallenge
{
    public function __construct(
        public readonly GameMatch $match,
        public readonly string $creatorToken,
    ) {
    }
}
