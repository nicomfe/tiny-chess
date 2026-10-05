<?php

declare(strict_types=1);

namespace Chess\Http;

use Chess\Game\ActionRejected;
use Chess\Game\ActionRejection;
use Chess\Game\Challenges;
use Chess\Game\Draws;
use Chess\Game\GameMatch;
use Chess\Game\MatchLinkFactory;
use Chess\Game\MatchSnapshot;
use Chess\Game\MatchClock;
use Chess\Game\MoveRejected;
use Chess\Game\MoveRejection;
use Chess\Game\Moves;
use Chess\Game\PublicMatchState;
use Chess\Game\Resignations;
use Chess\Game\Role;
use Chess\Game\Seat;
use Chess\Game\Seating;
use Chess\Game\UciMove;
use Chess\View\View;

final class MatchController
{
    public function __construct(
        private readonly Challenges $challenges,
        private readonly Seating $seating,
        private readonly Moves $moves,
        private readonly Resignations $resignations,
        private readonly Draws $draws,
        private readonly MatchClock $matchClock,
        private readonly MatchLinkFactory $linkFactory,
        private readonly View $view,
    ) {
    }

    /**
     * The play page only resolves who the visitor already is. Seating the
     * opponent happens in `join`, which the browser calls with POST so link
     * previews that fetch the URL cannot take the joiner seat.
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $match = $this->challenges->find($params['matchId']);
        if ($match === null) {
            return Response::html($this->view->render('not-found', ['title' => 'Game not found']), 404);
        }

        $creatorToken = $request->queryParam('token');
        $seat = $this->seating->resolve($match, $creatorToken, JoinerCookie::readFrom($request, $match->id));
        $links = $this->linkFactory->forRequestBaseUrl($request->baseUrl());

        $response = Response::html($this->view->render('game', [
            'title' => 'Chess challenge',
            'bodyClass' => 'game-page',
            'match' => $seat->match,
            'role' => $seat->role,
            'playUrl' => $links->playUrl($match->id),
            // Only the holder of the token can be shown the creator link.
            'creatorUrl' => $seat->role === Role::Creator && $creatorToken !== null
                ? $links->creatorUrl($match->id, $creatorToken)
                : null,
        ]));

        return $this->withSeatCookie($response, $request, $seat);
    }

    /**
     * Claims the open joiner seat for this browser. Only a real page load
     * should call this — not a link-preview fetch of the play URL.
     *
     * @param array<string, string> $params
     */
    public function join(Request $request, array $params): Response
    {
        $match = $this->challenges->find($params['matchId']);
        if ($match === null) {
            return Response::json(['error' => 'match_not_found'], 404);
        }

        $creatorToken = $request->queryParam('token');
        $seat = $this->seating->claim($match, $creatorToken, JoinerCookie::readFrom($request, $match->id));

        $response = Response::json([
            'role' => $seat->role->value,
            'status' => $seat->match->status->value,
        ]);

        return $this->withSeatCookie($response, $request, $seat);
    }

    /** @param array<string, string> $params */
    public function state(Request $request, array $params): Response
    {
        $snapshot = $this->challenges->snapshot($params['matchId']);
        if ($snapshot === null) {
            return Response::json(['error' => 'match_not_found'], 404);
        }

        return $this->stateResponse($request, $snapshot, $this->roleFor($request, $snapshot->match));
    }

    /** @param array<string, string> $params */
    public function submitMove(Request $request, array $params): Response
    {
        $match = $this->challenges->find($params['matchId']);
        if ($match === null) {
            return Response::json(['error' => 'match_not_found'], 404);
        }

        // Parsed at the edge, the way challenge input is, so the domain only
        // ever sees something that is at least shaped like a move.
        $move = UciMove::parse($request->bodyParam('uci'));
        if ($move === null) {
            return $this->rejection(MoveRejection::MalformedMove);
        }

        $role = $this->roleFor($request, $match);

        try {
            $snapshot = $this->moves->submit($match, $role, $move);
        } catch (MoveRejected $rejected) {
            return $this->rejection($rejected->reason);
        }

        // The mover gets the new state straight back, so their board does not
        // have to wait for the next poll to be sure the ply landed.
        return $this->stateResponse($request, $snapshot, $role);
    }

    /** @param array<string, string> $params */
    public function resign(Request $request, array $params): Response
    {
        $match = $this->challenges->find($params['matchId']);
        if ($match === null) {
            return Response::json(['error' => 'match_not_found'], 404);
        }

        $role = $this->roleFor($request, $match);

        try {
            $snapshot = $this->resignations->resign($match, $role);
        } catch (ActionRejected $rejected) {
            return $this->actionRejection($rejected->reason);
        }

        return $this->stateResponse($request, $snapshot, $role);
    }

    /** @param array<string, string> $params */
    public function draw(Request $request, array $params): Response
    {
        $match = $this->challenges->find($params['matchId']);
        if ($match === null) {
            return Response::json(['error' => 'match_not_found'], 404);
        }

        $role = $this->roleFor($request, $match);

        try {
            $snapshot = match ($request->bodyParam('action')) {
                'offer' => $this->draws->offer($match, $role),
                'accept' => $this->draws->accept($match, $role),
                'decline' => $this->draws->decline($match, $role),
                default => throw ActionRejected::because(ActionRejection::MalformedAction),
            };
        } catch (ActionRejected $rejected) {
            return $this->actionRejection($rejected->reason);
        }

        return $this->stateResponse($request, $snapshot, $role);
    }

    private function roleFor(Request $request, GameMatch $match): Role
    {
        return $this->seating->resolve(
            $match,
            $request->queryParam('token'),
            JoinerCookie::readFrom($request, $match->id),
        )->role;
    }

    private function stateResponse(Request $request, MatchSnapshot $snapshot, Role $role): Response
    {
        return Response::json(PublicMatchState::forRole(
            $snapshot,
            $role,
            $this->matchClock,
            $this->challenges->drawEventsFor($snapshot->match->id),
            max(0, (int) ($request->queryParam('since') ?? 0)),
            max(0, (int) ($request->queryParam('sinceDraw') ?? 0)),
        ));
    }

    private function rejection(MoveRejection $reason): Response
    {
        return Response::json(
            ['error' => $reason->value, 'message' => $reason->message()],
            self::statusFor($reason),
        );
    }

    private function actionRejection(ActionRejection $reason): Response
    {
        return Response::json(
            ['error' => $reason->value, 'message' => $reason->message()],
            self::statusForAction($reason),
        );
    }

    private static function statusFor(MoveRejection $reason): int
    {
        return match ($reason) {
            MoveRejection::MatchNotFound => 404,
            MoveRejection::NotAPlayer => 403,
            MoveRejection::MatchNotStarted, MoveRejection::MatchFinished, MoveRejection::NotYourTurn => 409,
            MoveRejection::MalformedMove, MoveRejection::IllegalMove => 422,
        };
    }

    private static function statusForAction(ActionRejection $reason): int
    {
        return match ($reason) {
            ActionRejection::MatchNotFound => 404,
            ActionRejection::NotAPlayer => 403,
            ActionRejection::MatchNotStarted, ActionRejection::MatchFinished, ActionRejection::NoDrawOffer, ActionRejection::OwnDrawOffer, ActionRejection::DrawAlreadyOffered => 409,
            ActionRejection::MalformedAction => 422,
        };
    }

    private function withSeatCookie(Response $response, Request $request, Seat $seat): Response
    {
        if ($seat->issuedJoinerToken === null) {
            return $response;
        }

        return $response->withCookie(
            JoinerCookie::issue($seat->match->id, $seat->issuedJoinerToken, $request->isSecure()),
        );
    }

}
