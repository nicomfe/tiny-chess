<?php

declare(strict_types=1);

namespace Chess\Game;

use InvalidArgumentException;
use PChess\Chess\Board;
use PChess\Chess\Chess;
use PChess\Chess\Piece;

/**
 * One position on the board, and the only place in the app that knows the rules
 * of chess — everything else deals in FEN and UCI.
 *
 * A position is a value: playing a move returns the position it leads to and
 * leaves this one alone. The rules engine is rebuilt per question, which costs
 * a FEN parse and keeps that value semantics honest.
 */
final class Position
{
    public const STARTING_FEN = Board::DEFAULT_POSITION;

    private function __construct(public readonly string $fen)
    {
    }

    /** @throws InvalidArgumentException when the engine cannot read the FEN */
    public static function fromFen(string $fen): self
    {
        new Chess($fen);

        return new self($fen);
    }

    public function sideToMove(): Color
    {
        return $this->engine()->turn === Piece::WHITE ? Color::White : Color::Black;
    }

    public function isCheck(): bool
    {
        return $this->engine()->inCheck();
    }

    /**
     * Every legal move, as the board widget wants them: each origin square
     * mapped to the squares it may reach. The four promotion choices collapse
     * into one destination, since picking the piece is a separate step.
     *
     * @return array<string, list<string>>
     */
    public function legalDestinations(): array
    {
        $reachable = [];
        foreach ($this->engine()->moves() as $move) {
            $reachable[$move->from][$move->to] = true;
        }

        return array_map(
            static fn (array $squares): array => array_keys($squares),
            $reachable,
        );
    }

    /** Null when the move is not legal here, which includes naming the wrong promotion. */
    public function play(UciMove $move): ?PlayedMove
    {
        $engine = $this->engine();

        // The engine's own lookup ignores a promotion piece on a move that
        // cannot promote, so the candidate is chosen here instead: a UCI string
        // has to name a promotion exactly when the move calls for one.
        $candidate = null;
        foreach ($engine->moves() as $legal) {
            if ($move->matches($legal->from, $legal->to, $legal->promotion)) {
                $candidate = $legal;
                break;
            }
        }

        if ($candidate === null) {
            return null;
        }

        $engine->move([
            'from' => $candidate->from,
            'to' => $candidate->to,
            'promotion' => $candidate->promotion,
        ]);

        return new PlayedMove($move, (string) $candidate->san, new self($engine->fen()));
    }

    private function engine(): Chess
    {
        return new Chess($this->fen);
    }
}
