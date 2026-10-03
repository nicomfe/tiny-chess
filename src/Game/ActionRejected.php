<?php

declare(strict_types=1);

namespace Chess\Game;

use RuntimeException;

/** Thrown instead of changing the match, so a refused resign or draw changes nothing. */
final class ActionRejected extends RuntimeException
{
    private function __construct(public readonly ActionRejection $reason)
    {
        parent::__construct($reason->message());
    }

    public static function because(ActionRejection $reason): self
    {
        return new self($reason);
    }
}
