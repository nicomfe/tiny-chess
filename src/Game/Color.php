<?php

declare(strict_types=1);

namespace Chess\Game;

enum Color: string
{
    case White = 'white';
    case Black = 'black';

    public function opposite(): self
    {
        return $this === self::White ? self::Black : self::White;
    }
}
