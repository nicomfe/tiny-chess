<?php

/**
 * Manual smoke check for the create-a-challenge flow, driven entirely through
 * HTTP so it only asserts externally visible behaviour.
 *
 *   php scripts/api-check.php [baseUrl]   # defaults to http://localhost:8080
 */

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://localhost:8080', '/');

$failures = 0;

function check(string $description, bool $passed, string $detail = ''): void
{
    global $failures;

    if ($passed) {
        fwrite(STDOUT, "  ok   {$description}\n");

        return;
    }

    $failures++;
    fwrite(STDOUT, "  FAIL {$description}" . ($detail === '' ? '' : " — {$detail}") . "\n");
}

/**
 * @param array<string, mixed>|null $jsonBody
 * @param array<string, mixed>|null $formBody
 * @return array{status: int, headers: array<int, string>, body: string, json: array<string, mixed>|null}
 */
function request(string $method, string $url, ?array $jsonBody = null, ?array $formBody = null): array
{
    $headers = ['Accept: application/json'];
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 10,
    ];

    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
        $options['content'] = json_encode($jsonBody, JSON_THROW_ON_ERROR);
    }

    if ($formBody !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options['content'] = http_build_query($formBody);
    }

    $options['header'] = implode("\r\n", $headers);

    $body = @file_get_contents($url, false, stream_context_create(['http' => $options]));
    if ($body === false) {
        fwrite(STDERR, "Request failed: {$method} {$url}\nIs the stack running? docker compose up -d\n");
        exit(2);
    }

    $responseHeaders = $http_response_header ?? [];
    preg_match('#HTTP/\S+\s+(\d{3})#', $responseHeaders[0] ?? '', $statusMatch);
    $decoded = json_decode($body, true);

    return [
        'status' => (int) ($statusMatch[1] ?? 0),
        'headers' => $responseHeaders,
        'body' => $body,
        'json' => is_array($decoded) ? $decoded : null,
    ];
}

/** @param array<int, string> $headers */
function header_value(array $headers, string $name): ?string
{
    foreach ($headers as $header) {
        if (stripos($header, $name . ':') === 0) {
            return trim(substr($header, strlen($name) + 1));
        }
    }

    return null;
}

fwrite(STDOUT, "Checking {$baseUrl}\n\nCreate a challenge via the API\n");

$created = request('POST', $baseUrl . '/api/challenges', ['minutes' => 5, 'white' => 'creator']);
check('returns 201', $created['status'] === 201, "got {$created['status']}");

$matchId = $created['json']['matchId'] ?? '';
$creatorUrl = $created['json']['creatorUrl'] ?? '';
$playUrl = $created['json']['playUrl'] ?? '';

check('match id is opaque, non-sequential', preg_match('/^[0-9a-f]{32}$/', (string) $matchId) === 1, (string) $matchId);
check('status is waiting', ($created['json']['status'] ?? null) === 'waiting');
check('time control is echoed back', ($created['json']['timeControl']['minutes'] ?? null) === 5);
check('creator plays white', ($created['json']['colors']['creator'] ?? null) === 'white');
check('play url is just the match id', $playUrl === $baseUrl . '/game/' . $matchId, (string) $playUrl);
check('creator url carries a token', str_starts_with((string) $creatorUrl, $playUrl . '?token='));
check('play url carries no token', !str_contains((string) $playUrl, 'token'));

$creatorToken = (string) parse_url((string) $creatorUrl, PHP_URL_QUERY);
$creatorToken = substr($creatorToken, strlen('token='));

fwrite(STDOUT, "\nIds are unique per challenge\n");
$second = request('POST', $baseUrl . '/api/challenges', ['minutes' => 10, 'white' => 'opponent']);
check('second challenge gets a different id', ($second['json']['matchId'] ?? '') !== $matchId);
check('10 minute option is accepted', ($second['json']['timeControl']['minutes'] ?? null) === 10);
check('opponent can be given white', ($second['json']['colors']['creator'] ?? null) === 'black');

fwrite(STDOUT, "\nOnly 5 and 10 minutes, only two color choices\n");
foreach ([3, 15, 0] as $minutes) {
    $rejected = request('POST', $baseUrl . '/api/challenges', ['minutes' => $minutes, 'white' => 'creator']);
    check("{$minutes} minutes is rejected with 422", $rejected['status'] === 422, "got {$rejected['status']}");
}
$badColor = request('POST', $baseUrl . '/api/challenges', ['minutes' => 5, 'white' => 'nobody']);
check('unknown color choice is rejected with 422', $badColor['status'] === 422, "got {$badColor['status']}");

fwrite(STDOUT, "\nRole resolution from the saved creator link\n");
$asCreator = request('GET', $baseUrl . '/api/matches/' . $matchId . '?token=' . urlencode($creatorToken));
check('creator token resolves to the creator role', ($asCreator['json']['you']['role'] ?? null) === 'creator');
check('the stored color assignment is readable', ($asCreator['json']['colors']['creator'] ?? null) === 'white');

$asVisitor = request('GET', $baseUrl . '/api/matches/' . $matchId);
check('no token means no creator role', ($asVisitor['json']['you']['role'] ?? null) === 'spectator');

$withWrongToken = request('GET', $baseUrl . '/api/matches/' . $matchId . '?token=not-the-real-token');
check('a wrong token does not grant the creator role', ($withWrongToken['json']['you']['role'] ?? null) === 'spectator');

fwrite(STDOUT, "\nThe creator token stays secret\n");
check('public state leaks no token or hash', !preg_match('/token_hash|creatorToken/i', $asVisitor['body']), $asVisitor['body']);
check('public state does not contain the raw token', !str_contains($asVisitor['body'], $creatorToken));

$publicPage = request('GET', $playUrl);
check('play link page does not contain the creator token', !str_contains($publicPage['body'], $creatorToken));

fwrite(STDOUT, "\nPretty URLs and HTML pages\n");
check('create form responds at /', request('GET', $baseUrl . '/')['status'] === 200);
check('play page responds at /game/{matchId}', $publicPage['status'] === 200, "got {$publicPage['status']}");
$creatorPage = request('GET', (string) $creatorUrl);
check('creator page labels "Your link"', str_contains($creatorPage['body'], 'Your link'));
check('creator page labels "Opponent link"', str_contains($creatorPage['body'], 'Opponent link'));
check('creator page shows the shareable play url', str_contains($creatorPage['body'], (string) $playUrl));

fwrite(STDOUT, "\nUnknown matches\n");
$unknown = request('GET', $baseUrl . '/api/matches/' . str_repeat('a', 32));
check('unknown match id returns 404', $unknown['status'] === 404, "got {$unknown['status']}");
$malformed = request('GET', $baseUrl . '/api/matches/1');
check('malformed match id returns 404', $malformed['status'] === 404, "got {$malformed['status']}");

fwrite(STDOUT, "\nWrong methods and missing pages\n");
check('posting to a play link is rejected', request('POST', $playUrl, null, [])['status'] === 405);
check('an unknown path is a 404', request('GET', $baseUrl . '/nope')['status'] === 404);

fwrite(STDOUT, "\nForm submission\n");
$formPost = request('POST', $baseUrl . '/challenges', null, ['minutes' => '10', 'white' => 'opponent']);
check('form post redirects', $formPost['status'] === 303, "got {$formPost['status']}");
$redirect = (string) header_value($formPost['headers'], 'Location');
check(
    'form post lands on the creator link',
    preg_match('#^' . preg_quote($baseUrl, '#') . '/game/[0-9a-f]{32}\?token=.+$#', $redirect) === 1,
    $redirect,
);

$invalidFormPost = request('POST', $baseUrl . '/challenges', null, ['minutes' => '7', 'white' => 'creator']);
check('form post with a disallowed time returns 422', $invalidFormPost['status'] === 422, "got {$invalidFormPost['status']}");

fwrite(STDOUT, "\n" . ($failures === 0 ? "All checks passed.\n" : "{$failures} check(s) failed.\n"));
exit($failures === 0 ? 0 : 1);
