<?php

declare(strict_types=1);

namespace Chess\Http;

/**
 * The cookie that remembers a browser is the opponent in one particular match.
 *
 * It is named per match so the same browser can be the opponent in several
 * games at once, and it is the only proof of the joiner role: the spec gives
 * the joiner no recovery path, so losing it means becoming a spectator.
 */
final class JoinerCookie
{
    private const NAME_PREFIX = 'joiner_';

    /** Comfortably outlives a 5 or 10 minute game plus any reading of the replay. */
    private const LIFETIME_SECONDS = 7 * 24 * 60 * 60;

    public static function readFrom(Request $request, string $matchId): ?string
    {
        return $request->cookie(self::nameFor($matchId));
    }

    public static function issue(string $matchId, string $rawToken, bool $secure): Cookie
    {
        return new Cookie(self::nameFor($matchId), $rawToken, self::LIFETIME_SECONDS, $secure);
    }

    private static function nameFor(string $matchId): string
    {
        return self::NAME_PREFIX . $matchId;
    }
}
