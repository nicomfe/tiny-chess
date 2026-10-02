<?php

declare(strict_types=1);

namespace Chess\Game;

use RuntimeException;

final class InvalidChallengeInput extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
