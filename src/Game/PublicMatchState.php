<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * The match as anyone is allowed to see it. Token hashes are deliberately
 * absent: this payload is what spectators receive.
 *
 * It is also the whole poll contract, so a client that has it needs nothing
 * else to draw the board.
 */
final class PublicMatchState
{
    /**
     * @param int $cursor the newest move number the caller already has
     * @return array<string, mixed>
     */
    public static function forRole(MatchSnapshot $snapshot, Role $role, MatchClock $clock, int $cursor = 0): array
    {
        $match = $snapshot->match;
        $moves = $snapshot->moves;
        $position = $match->position();
        $color = $match->colorFor($role);
        $canMove = $color !== null && $match->allowsMoves() && $position->sideToMove() === $color;
        $clocks = $clock->display($match, $position->sideToMove());

        return [
            'matchId' => $match->id,
            'status' => $match->status->value,
            'timeControl' => ['minutes' => $match->timeControl->minutes()],
            'colors' => [
                'creator' => $match->creatorColor->value,
                'joiner' => $match->joinerColor()->value,
            ],
            'createdAt' => $match->createdAt->format(DATE_ATOM),
            'fen' => $match->fen,
            'turn' => $position->sideToMove()->value,
            'check' => $position->isCheck(),
            'moveCount' => $moves->count(),
            'moves' => array_map(
                static fn (RecordedMove $move): array => [
                    'number' => $move->number,
                    'uci' => $move->uci,
                    'san' => $move->san,
                ],
                $moves->since($cursor),
            ),
            'you' => [
                'role' => $role->value,
                'color' => $color?->value,
                'canMove' => $canMove,
            ],
            // Only the side to move is told where its pieces may go, which is
            // also what leaves a spectator's board with nothing to drag.
            'dests' => $canMove ? $position->legalDestinations() : null,
            'clocks' => [
                'white' => $clocks['white'],
                'black' => $clocks['black'],
                'running' => $clocks['running'],
            ],
            'result' => self::result($match),
            'drawOffer' => $match->drawOfferBy === null ? null : ['by' => $match->drawOfferBy->value],
        ];
    }

    /** @return array{winner: string, reason: string}|null */
    private static function result(GameMatch $match): ?array
    {
        if ($match->status !== MatchStatus::Finished || $match->resultReason === null) {
            return null;
        }

        return [
            'winner' => $match->resultWinner?->value,
            'reason' => $match->resultReason->value,
        ];
    }
}
