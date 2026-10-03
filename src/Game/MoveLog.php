<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * The plies of a match in order, and what is worth sending a client that
 * already has some of them.
 *
 * A game's worth of plies is a few hundred bytes, so the whole log is loaded
 * and sliced here rather than asked for twice.
 */
final class MoveLog
{
    /** @param list<RecordedMove> $moves */
    public function __construct(private readonly array $moves)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function count(): int
    {
        return count($this->moves);
    }

    /**
     * The plies after the given move number, so a poll that is up to date
     * carries no move list at all.
     *
     * @return list<RecordedMove>
     */
    public function since(int $cursor): array
    {
        return array_values(array_filter(
            $this->moves,
            static fn (RecordedMove $move): bool => $move->number > $cursor,
        ));
    }
}
