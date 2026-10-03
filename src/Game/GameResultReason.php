<?php

declare(strict_types=1);

namespace Chess\Game;

/** Why a finished match ended the way it did. */
enum GameResultReason: string
{
    case Checkmate = 'checkmate';
    case Stalemate = 'stalemate';
    case InsufficientMaterial = 'insufficient_material';
    case Timeout = 'timeout';
    case Resign = 'resign';
    case Agreement = 'agreement';
}
