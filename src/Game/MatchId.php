<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * Match ids are opaque 128-bit random hex strings: they are shared publicly as
 * the play link, so they must not be guessable or enumerable.
 */
final class MatchId
{
    private const LENGTH = 32;

    public static function generate(): string
    {
        return bin2hex(random_bytes(self::LENGTH / 2));
    }

    public static function isWellFormed(string $candidate): bool
    {
        return preg_match('/^[0-9a-f]{' . self::LENGTH . '}$/', $candidate) === 1;
    }
}
