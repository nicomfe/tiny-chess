<?php

declare(strict_types=1);

namespace Chess\Game;

use Chess\Clock;
use Chess\Security\Tokens;

/** Creates challenges and answers "who is this visitor?" for an existing one. */
final class Challenges
{
    public function __construct(
        private readonly MatchRepository $matches,
        private readonly Tokens $tokens,
        private readonly Clock $clock,
    ) {
    }

    public function create(TimeControl $timeControl, Color $creatorColor): CreatedChallenge
    {
        $creatorToken = $this->tokens->issue();

        $match = new GameMatch(
            id: MatchId::generate(),
            status: MatchStatus::Waiting,
            timeControl: $timeControl,
            creatorColor: $creatorColor,
            creatorTokenHash: $this->tokens->hash($creatorToken),
            createdAt: $this->clock->now(),
        );

        $this->matches->insert($match);

        return new CreatedChallenge($match, $creatorToken);
    }

    public function find(string $matchId): ?GameMatch
    {
        return $this->matches->find($matchId);
    }

    /**
     * The creator token in the URL is the only thing that proves the creator
     * role, which is what lets a saved creator link survive lost cookies.
     */
    public function roleFor(GameMatch $match, ?string $creatorToken): Role
    {
        if ($creatorToken !== null && $this->tokens->verify($creatorToken, $match->creatorTokenHash)) {
            return Role::Creator;
        }

        return Role::Spectator;
    }
}
