<?php

declare(strict_types=1);

namespace Chess\Game;

/**
 * A move in the notation the database stores: origin square, target square, and
 * the promotion piece when one is named — `e2e4` or `e7e8q`.
 *
 * Being a valid UCI string says nothing about being a legal move; that is
 * `Position`'s question.
 */
final class UciMove
{
    private function __construct(
        public readonly string $uci,
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $promotion,
    ) {
    }

    /** Null when the text is not a move at all, e.g. `e2e9`, `e2e4k` or `resign`. */
    public static function parse(?string $uci): ?self
    {
        if ($uci === null || preg_match('/^([a-h][1-8])([a-h][1-8])([qrbn]?)$/', strtolower($uci), $parts) !== 1) {
            return null;
        }

        [, $from, $to, $promotion] = $parts;
        if ($from === $to) {
            return null;
        }

        return new self($from . $to . $promotion, $from, $to, $promotion === '' ? null : $promotion);
    }

    public function matches(string $from, string $to, ?string $promotion): bool
    {
        return $this->from === $from && $this->to === $to && $this->promotion === $promotion;
    }
}
