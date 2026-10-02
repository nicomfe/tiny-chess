<?php

declare(strict_types=1);

namespace Chess\Http;

use Chess\Game\ChallengeInput;
use Chess\Game\Challenges;
use Chess\Game\CreatedChallenge;
use Chess\Game\InvalidChallengeInput;
use Chess\Game\MatchLinkFactory;
use Chess\Game\PublicMatchState;
use Chess\Game\Role;
use Chess\View\View;

final class ChallengeController
{
    public function __construct(
        private readonly Challenges $challenges,
        private readonly MatchLinkFactory $linkFactory,
        private readonly View $view,
    ) {
    }

    public function showCreateForm(Request $request): Response
    {
        return Response::html($this->view->render('create', ['title' => 'New chess challenge']));
    }

    public function create(Request $request): Response
    {
        try {
            $input = ChallengeInput::parse($request->bodyParam('minutes'), $request->bodyParam('white'));
        } catch (InvalidChallengeInput $e) {
            return Response::html(
                $this->view->render('create', [
                    'title' => 'New chess challenge',
                    'error' => $e->getMessage(),
                    'minutes' => $request->bodyParam('minutes'),
                    'white' => $request->bodyParam('white'),
                ]),
                422,
            );
        }

        $created = $this->challenges->create($input->timeControl, $input->creatorColor);
        $links = $this->linkFactory->forRequestBaseUrl($request->baseUrl());

        return Response::redirect($links->creatorUrl($created->match->id, $created->creatorToken));
    }

    public function createViaApi(Request $request): Response
    {
        try {
            $input = ChallengeInput::parse($request->bodyParam('minutes'), $request->bodyParam('white'));
        } catch (InvalidChallengeInput $e) {
            return Response::json([
                'error' => 'invalid_input',
                'field' => $e->field,
                'message' => $e->getMessage(),
            ], 422);
        }

        $created = $this->challenges->create($input->timeControl, $input->creatorColor);

        return Response::json($this->payloadFor($created, $request), 201);
    }

    /**
     * The same shape a later state read returns, plus the two links — the only
     * moment the raw creator token is ever sent anywhere.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(CreatedChallenge $created, Request $request): array
    {
        $match = $created->match;
        $links = $this->linkFactory->forRequestBaseUrl($request->baseUrl());

        return PublicMatchState::forRole($match, Role::Creator) + [
            'creatorUrl' => $links->creatorUrl($match->id, $created->creatorToken),
            'playUrl' => $links->playUrl($match->id),
        ];
    }
}
