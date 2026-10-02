<?php

declare(strict_types=1);

namespace Chess\Game;

/** The only two choices a creator makes, validated against the allowed options. */
final class ChallengeInput
{
    private function __construct(
        public readonly TimeControl $timeControl,
        public readonly Color $creatorColor,
    ) {
    }

    /** @throws InvalidChallengeInput */
    public static function parse(?string $minutes, ?string $white): self
    {
        $timeControl = $minutes === null ? null : TimeControl::fromMinutes($minutes);
        if ($timeControl === null) {
            throw new InvalidChallengeInput('minutes', 'Choose either 5 or 10 minutes per player.');
        }

        $creatorColor = match ($white) {
            'creator' => Color::White,
            'opponent' => Color::Black,
            default => null,
        };
        if ($creatorColor === null) {
            throw new InvalidChallengeInput('white', 'Choose whether you or your opponent plays white.');
        }

        return new self($timeControl, $creatorColor);
    }
}
