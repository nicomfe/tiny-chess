<?php

declare(strict_types=1);

namespace Chess\Game;

use RuntimeException;

/** Thrown instead of changing the position, so a refused attempt changes nothing. */
final class MoveRejected extends RuntimeException
{
    private function __construct(public readonly MoveRejection $reason)
    {
        parent::__construct($reason->message());
    }

    public static function because(MoveRejection $reason): self
    {
        return new self($reason);
    }
}
