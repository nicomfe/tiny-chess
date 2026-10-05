<?php

declare(strict_types=1);

namespace Chess\Game;

enum DrawEventKind: string
{
    case Offer = 'offer';
    case Accept = 'accept';
    case Decline = 'decline';
}
