<?php

declare(strict_types=1);

namespace Chess\Game;

/** Why a finished match ended the way it did. More reasons land in later tickets. */
enum GameResultReason: string
{
    case Timeout = 'timeout';
}
