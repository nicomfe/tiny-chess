<?php

declare(strict_types=1);

use Chess\App;
use Chess\Http\Request;
use Chess\Http\Response;

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';

try {
    $response = App::boot($projectRoot)->handle(Request::fromGlobals());
} catch (Throwable $e) {
    // Details stay in the log: request URIs carry creator tokens and exception
    // messages can carry the DSN, so neither belongs in a response body.
    error_log(sprintf('Unhandled %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

    $response = Response::json(['error' => 'server_error'], 500);
}

$response->send();
