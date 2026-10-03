<?php

declare(strict_types=1);

namespace Chess\Game;

/** Every reason a submitted move can fail, in terms the mover can be shown. */
enum MoveRejection: string
{
    case MatchNotFound = 'match_not_found';
    case NotAPlayer = 'not_a_player';
    case MatchNotStarted = 'match_not_started';
    case MatchFinished = 'match_finished';
    case NotYourTurn = 'not_your_turn';
    case MalformedMove = 'malformed_move';
    case IllegalMove = 'illegal_move';

    public function message(): string
    {
        return match ($this) {
            self::MatchNotFound => 'This game no longer exists.',
            self::NotAPlayer => 'Only the two seated players can move — you are watching this game.',
            self::MatchNotStarted => 'Both players have to be seated before a move can be played.',
            self::MatchFinished => 'This game is over, so the position cannot change.',
            self::NotYourTurn => 'It is not your turn.',
            self::MalformedMove => 'That is not a move this board understands.',
            self::IllegalMove => 'That move is not legal in this position.',
        };
    }
}
