<?php

declare(strict_types=1);

namespace Chess\Game;

/** Every reason a resign or draw action can fail, in terms the caller can be shown. */
enum ActionRejection: string
{
    case MatchNotFound = 'match_not_found';
    case NotAPlayer = 'not_a_player';
    case MatchNotStarted = 'match_not_started';
    case MatchFinished = 'match_finished';
    case MalformedAction = 'malformed_action';
    case NoDrawOffer = 'no_draw_offer';
    case OwnDrawOffer = 'own_draw_offer';
    case DrawAlreadyOffered = 'draw_already_offered';

    public function message(): string
    {
        return match ($this) {
            self::MatchNotFound => 'This game no longer exists.',
            self::NotAPlayer => 'Only the two seated players can do that — you are watching this game.',
            self::MatchNotStarted => 'Both players have to be seated before the game can be ended this way.',
            self::MatchFinished => 'This game is over.',
            self::MalformedAction => 'That is not a draw action this game understands.',
            self::NoDrawOffer => 'There is no draw offer to answer.',
            self::OwnDrawOffer => 'Your opponent has to answer the draw offer.',
            self::DrawAlreadyOffered => 'There is already a draw offer on the table.',
        };
    }
}
