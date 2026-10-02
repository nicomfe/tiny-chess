<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * The match as anyone is allowed to see it. Token hashes are deliberately
 * absent: this payload is what spectators receive.
 */
final class PublicMatchState
{
    /** @return array<string, mixed> */
    public static function forRole(GameMatch $match, Role $role): array
    {
        return [
            'matchId' => $match->id,
            'status' => $match->status->value,
            'timeControl' => ['minutes' => $match->timeControl->minutes()],
            'colors' => [
                'creator' => $match->creatorColor->value,
                'joiner' => $match->joinerColor()->value,
            ],
            'createdAt' => $match->createdAt->format(DATE_ATOM),
            'you' => ['role' => $role->value],
        ];
    }
}
