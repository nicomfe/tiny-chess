<?php

declare(strict_types=1);

namespace Chess\Game;

/** Builds the two links a challenge hands out. */
final class MatchLinks
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    /** Safe to share: identifies the match but carries no secret. */
    public function playUrl(string $matchId): string
    {
        return $this->baseUrl . '/game/' . $matchId;
    }

    /** Private to the creator: carries the secret token. */
    public function creatorUrl(string $matchId, string $creatorToken): string
    {
        return $this->playUrl($matchId) . '?token=' . urlencode($creatorToken);
    }
}
