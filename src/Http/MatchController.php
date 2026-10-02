<?php

declare(strict_types=1);

namespace Chess\Http;

use Chess\Game\Challenges;
use Chess\Game\MatchLinkFactory;
use Chess\Game\PublicMatchState;
use Chess\Game\Role;
use Chess\Game\Seat;
use Chess\Game\Seating;
use Chess\View\View;

final class MatchController
{
    public function __construct(
        private readonly Challenges $challenges,
        private readonly Seating $seating,
        private readonly MatchLinkFactory $linkFactory,
        private readonly View $view,
    ) {
    }

    /**
     * Opening the play link is what seats the opponent, so this is the one
     * place that claims a seat.
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
        $seat = $this->seating->claim($match, $creatorToken, JoinerCookie::readFrom($request, $match->id));
        $links = $this->linkFactory->forRequestBaseUrl($request->baseUrl());

        $response = Response::html($this->view->render('game', [
            'title' => 'Chess challenge',
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

    /** @param array<string, string> $params */
    public function state(Request $request, array $params): Response
    {
        $match = $this->challenges->find($params['matchId']);
        if ($match === null) {
            return Response::json(['error' => 'match_not_found'], 404);
        }

        $seat = $this->seating->resolve(
            $match,
            $request->queryParam('token'),
            JoinerCookie::readFrom($request, $match->id),
        );

        return Response::json(PublicMatchState::forRole($seat->match, $seat->role));
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
