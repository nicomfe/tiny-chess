<?php

declare(strict_types=1);

namespace Chess\Game;

enum Role: string
{
    case Creator = 'creator';
    case Joiner = 'joiner';
    case Spectator = 'spectator';
}
