<?php

declare(strict_types=1);

namespace Chess\Game;

use Chess\Clock;
use Chess\Security\Tokens;

/** Creates challenges and looks them up again; seating is `Seating`'s job. */
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
}
