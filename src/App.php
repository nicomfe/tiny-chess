<?php

declare(strict_types=1);

namespace Chess;

use Chess\Game\Challenges;
use Chess\Game\Draws;
use Chess\Game\MatchLinkFactory;
use Chess\Game\MatchClock;
use Chess\Game\MatchRepository;
use Chess\Game\MatchTiming;
use Chess\Game\Moves;
use Chess\Game\Resignations;
use Chess\Game\Seating;
use Chess\Http\ChallengeController;
use Chess\Http\MatchController;
use Chess\Http\MethodNotAllowed;
use Chess\Http\Request;
use Chess\Http\Response;
use Chess\Http\Router;
use Chess\Security\Tokens;
use Chess\View\View;

final class App
{
    private function __construct(
        private readonly Router $router,
        private readonly View $view,
    ) {
    }

    public static function boot(string $projectRoot): self
    {
        $config = Config::load($projectRoot);

        $matches = new MatchRepository(Database::connect($config));
        $tokens = new Tokens($config->appSecret());
        $clock = new Clock();

        $matchClock = new MatchClock($clock);
        $timing = new MatchTiming($matches, $matchClock, $clock);
        $challenges = new Challenges($matches, $tokens, $clock, $timing);
        $seating = new Seating($matches, $tokens);
        $moves = new Moves($matches, $clock, $timing);
        $resignations = new Resignations($matches, $matchClock, $timing);
        $draws = new Draws($matches, $matchClock, $timing);
        $linkFactory = new MatchLinkFactory($config->baseUrlOverride());
        $view = new View($projectRoot . '/templates');

        $challengeController = new ChallengeController($challenges, $matchClock, $linkFactory, $view);
        $matchController = new MatchController($challenges, $seating, $moves, $resignations, $draws, $matchClock, $linkFactory, $view);
        $router = new Router();
        $router->add('GET', '/', $challengeController->showCreateForm(...));
        $router->add('POST', '/challenges', $challengeController->create(...));
        $router->add('POST', '/api/challenges', $challengeController->createViaApi(...));
        $router->add('GET', '/game/{matchId}', $matchController->show(...));
        $router->add('POST', '/api/matches/{matchId}/join', $matchController->join(...));
        $router->add('GET', '/api/matches/{matchId}', $matchController->state(...));
        $router->add('POST', '/api/matches/{matchId}/moves', $matchController->submitMove(...));
        $router->add('POST', '/api/matches/{matchId}/resign', $matchController->resign(...));
        $router->add('POST', '/api/matches/{matchId}/draw', $matchController->draw(...));
        return new self($router, $view);
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (MethodNotAllowed) {
            return $this->error($request, 405, 'method_not_allowed');
        }

        return $response ?? $this->error($request, 404, 'not_found');
    }

    private function error(Request $request, int $status, string $code): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $code], $status);
        }

        return Response::html($this->view->render('not-found', ['title' => 'Page not found']), $status);
    }
}
