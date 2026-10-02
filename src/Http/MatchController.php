<?php

declare(strict_types=1);

namespace Chess\Http;

use Chess\Game\Challenges;
use Chess\Game\MatchLinkFactory;
use Chess\Game\PublicMatchState;
use Chess\Game\Role;
use Chess\View\View;

final class MatchController
{
    public function __construct(
        private readonly Challenges $challenges,
        private readonly MatchLinkFactory $linkFactory,
        private readonly View $view,
    ) {
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $match = $this->challenges->find($params['matchId']);
        if ($match === null) {
            return Response::html($this->view->render('not-found', ['title' => 'Game not found']), 404);
        }

        $creatorToken = $request->queryParam('token');
        $role = $this->challenges->roleFor($match, $creatorToken);
        $links = $this->linkFactory->forRequestBaseUrl($request->baseUrl());

        return Response::html($this->view->render('game', [
            'title' => 'Chess challenge',
            'match' => $match,
            'role' => $role,
            'playUrl' => $links->playUrl($match->id),
            // Only the holder of the token can be shown the creator link.
            'creatorUrl' => $role === Role::Creator && $creatorToken !== null
                ? $links->creatorUrl($match->id, $creatorToken)
                : null,
        ]));
    }

    /** @param array<string, string> $params */
    public function state(Request $request, array $params): Response
    {
        $match = $this->challenges->find($params['matchId']);
        if ($match === null) {
            return Response::json(['error' => 'match_not_found'], 404);
        }

        $role = $this->challenges->roleFor($match, $request->queryParam('token'));

        return Response::json(PublicMatchState::forRole($match, $role));
    }
}
