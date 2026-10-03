<?php

/**
 * Manual smoke check for the create-a-challenge flow, driven entirely through
 * HTTP so it only asserts externally visible behaviour.
 *
 *   php scripts/api-check.php [baseUrl]   # defaults to http://localhost:8080
 */

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://localhost:8080', '/');

const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

/**
 * White queens on b7xa8 after this line, so promotion checks have a position to
 * promote from: 1. e4 d5 2. exd5 c6 3. dxc6 Nf6 4. cxb7 a6.
 */
const PROMOTION_LINE = ['e2e4', 'd7d5', 'e4d5', 'c7c6', 'd5c6', 'g8f6', 'c6b7', 'a7a6'];

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
function request(string $method, string $url, ?array $jsonBody = null, ?array $formBody = null, ?string $cookie = null): array
{
    $headers = ['Accept: application/json'];
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 10,
    ];

    if ($cookie !== null) {
        $headers[] = 'Cookie: ' . $cookie;
    }

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

/**
 * The whole `Set-Cookie` line for the first cookie whose name starts with the
 * given prefix, so checks can assert on attributes as well as the value.
 *
 * @param array<int, string> $headers
 */
function set_cookie_header(array $headers, string $namePrefix): ?string
{
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie:') === 0
            && str_starts_with(ltrim(substr($header, strlen('Set-Cookie:'))), $namePrefix)) {
            return trim(substr($header, strlen('Set-Cookie:')));
        }
    }

    return null;
}

/** The `name=value` pair of a `Set-Cookie` line, ready to send back as a `Cookie` header. */
function cookie_pair(?string $setCookie): ?string
{
    if ($setCookie === null) {
        return null;
    }

    return trim(explode(';', $setCookie, 2)[0]);
}

/** The raw creator token out of a creator URL. */
function creator_token_from(string $creatorUrl): string
{
    $query = (string) parse_url($creatorUrl, PHP_URL_QUERY);

    return urldecode(substr($query, strlen('token=')));
}

/**
 * The game page renders its role and status as data attributes, so checks can
 * assert on the page without depending on the wording shown to players.
 */
function page_attribute(string $body, string $attribute): ?string
{
    preg_match('/data-' . preg_quote($attribute, '/') . '="([^"]*)"/', $body, $found);

    return $found[1] ?? null;
}

/**
 * Public state for a match, as whoever the given credentials make the caller.
 *
 * @return array{status: int, headers: array<int, string>, body: string, json: array<string, mixed>|null}
 */
function match_state(string $baseUrl, string $matchId, ?string $joinerCookie = null, string $creatorToken = ''): array
{
    $url = $baseUrl . '/api/matches/' . $matchId
        . ($creatorToken === '' ? '' : '?token=' . urlencode($creatorToken));

    return request('GET', $url, null, null, $joinerCookie);
}

/**
 * A fresh challenge, so each section starts from a known seating state.
 *
 * @return array{matchId: string, playUrl: string, creatorUrl: string, creatorToken: string}
 */
function create_challenge(string $baseUrl): array
{
    $created = request('POST', $baseUrl . '/api/challenges', ['minutes' => 5, 'white' => 'creator']);
    $creatorUrl = (string) ($created['json']['creatorUrl'] ?? '');

    return [
        'matchId' => (string) ($created['json']['matchId'] ?? ''),
        'playUrl' => (string) ($created['json']['playUrl'] ?? ''),
        'creatorUrl' => $creatorUrl,
        'creatorToken' => creator_token_from($creatorUrl),
    ];
}

/**
 * A fresh challenge with the opponent already seated, so move checks start from
 * a match that allows play. The creator has white.
 *
 * @return array{matchId: string, playUrl: string, creatorUrl: string, creatorToken: string, joinerCookie: string}
 */
function seated_game(string $baseUrl): array
{
    $game = create_challenge($baseUrl);
    $joined = request('GET', $game['playUrl']);

    return $game + ['joinerCookie' => (string) cookie_pair(set_cookie_header($joined['headers'], 'joiner'))];
}

/**
 * Submits one UCI move as whoever the given credentials make the caller.
 *
 * @return array{status: int, headers: array<int, string>, body: string, json: array<string, mixed>|null}
 */
function submit_move(string $baseUrl, string $matchId, string $uci, ?string $joinerCookie = null, string $creatorToken = ''): array
{
    $url = $baseUrl . '/api/matches/' . $matchId . '/moves'
        . ($creatorToken === '' ? '' : '?token=' . urlencode($creatorToken));

    return request('POST', $url, ['uci' => $uci], null, $joinerCookie);
}

/**
 * Plays a line from the starting position, white first, so a check can set up a
 * specific position before testing it. Reports whether every ply was accepted.
 *
 * @param array{matchId: string, creatorToken: string, joinerCookie: string} $game
 * @param list<string> $line
 */
function play_line(string $baseUrl, array $game, array $line): bool
{
    foreach ($line as $ply => $uci) {
        $played = $ply % 2 === 0
            ? submit_move($baseUrl, $game['matchId'], $uci, null, $game['creatorToken'])
            : submit_move($baseUrl, $game['matchId'], $uci, $game['joinerCookie']);

        if ($played['status'] !== 200) {
            return false;
        }
    }

    return true;
}

/**
 * The newest ply in a state payload.
 *
 * @param array<string, mixed>|null $state
 * @return array<string, mixed>
 */
function last_move(?array $state): array
{
    $moves = $state['moves'] ?? [];

    return is_array($moves) && $moves !== [] ? (array) end($moves) : [];
}

/**
 * Whether a field is present and explicitly null — which `??` cannot tell apart
 * from a missing key, and the difference is the whole point for the fields that
 * later tickets will fill in.
 *
 * @param array<string, mixed>|null $payload
 */
function is_null_field(?array $payload, string $field): bool
{
    return is_array($payload) && array_key_exists($field, $payload) && $payload[$field] === null;
}

/** Five-minute control in milliseconds, matching `create_challenge()`. */
const FULL_BANK_MS = 300_000;

/** @param array<string, mixed>|null $state */
function clocks_are_idle_at_full(?array $state): bool
{
    $clocks = $state['clocks'] ?? null;

    return is_array($clocks)
        && (int) ($clocks['white'] ?? 0) === FULL_BANK_MS
        && (int) ($clocks['black'] ?? 0) === FULL_BANK_MS
        && array_key_exists('running', $clocks)
        && $clocks['running'] === null;
}

function db(): ?PDO
{
    static $pdo = null;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }

    $attempted = true;

    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;port=3307;dbname=chess;charset=utf8mb4',
            'chess',
            'chess',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );
    } catch (PDOException) {
        $pdo = null;
    }

    return $pdo;
}

/** Forces the side to move to have no time left, for timeout checks. */
function force_flag(string $matchId, string $side): bool
{
    $pdo = db();
    if ($pdo === null) {
        return false;
    }

    $column = $side === 'white' ? 'white_remaining_ms' : 'black_remaining_ms';
    $statement = $pdo->prepare(
        "UPDATE matches
            SET {$column} = 0,
                turn_started_at = UTC_TIMESTAMP(3) - INTERVAL 2 SECOND
          WHERE id = :id",
    );
    $statement->execute(['id' => $matchId]);

    return $statement->rowCount() === 1;
}

/**
 * Fires several POSTs at the same time, so a check can see what the server does
 * when two submissions race for one turn.
 *
 * @param list<array{url: string, body: array<string, mixed>}> $posts
 * @return list<int> the response status codes, in the order given
 */
function post_at_once(array $posts): array
{
    $multi = curl_multi_init();
    $handles = [];

    foreach ($posts as $post) {
        $handle = curl_init($post['url']);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($post['body'], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_multi_add_handle($multi, $handle);
        $handles[] = $handle;
    }

    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi);
    } while ($running > 0);

    $statuses = [];
    foreach ($handles as $handle) {
        $statuses[] = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
    }
    curl_multi_close($multi);

    return $statuses;
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

$creatorToken = creator_token_from((string) $creatorUrl);

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

fwrite(STDOUT, "\nWaiting for an opponent\n");
$game = create_challenge($baseUrl);

$creatorWaiting = request('GET', $game['creatorUrl']);
check('the creator page says it is waiting', page_attribute($creatorWaiting['body'], 'status') === 'waiting');
check('the creator is told an opponent is missing', stripos($creatorWaiting['body'], 'waiting for the opponent') !== false);
check('the creator link does not claim the joiner seat', set_cookie_header($creatorWaiting['headers'], 'joiner') === null);
check(
    'the match stays waiting until someone joins',
    (match_state($baseUrl, $game['matchId'], null, $game['creatorToken'])['json']['status'] ?? null) === 'waiting',
);

fwrite(STDOUT, "\nThe first play-link visitor is seated as joiner\n");
$joined = request('GET', $game['playUrl']);
$joinerSetCookie = set_cookie_header($joined['headers'], 'joiner');
$joinerCookie = (string) cookie_pair($joinerSetCookie);
[$joinerCookieName, $joinerToken] = explode('=', $joinerCookie, 2) + ['', ''];
check('the play link issues a joiner cookie', $joinerSetCookie !== null, implode(' | ', $joined['headers']));
check('the joiner cookie is httpOnly', stripos((string) $joinerSetCookie, 'HttpOnly') !== false, (string) $joinerSetCookie);
check('the joiner cookie survives the link being clicked from elsewhere', stripos((string) $joinerSetCookie, 'SameSite=Lax') !== false, (string) $joinerSetCookie);
check('the play page speaks to the opponent as the joiner', page_attribute($joined['body'], 'role') === 'joiner');

$asJoiner = match_state($baseUrl, $game['matchId'], $joinerCookie);
check('the cookie resolves to the joiner role', ($asJoiner['json']['you']['role'] ?? null) === 'joiner', $asJoiner['body']);
check('the match is ready once both are seated', ($asJoiner['json']['status'] ?? null) === 'ready');
check('clocks stay idle at the full bank while ready', clocks_are_idle_at_full($asJoiner['json']), $asJoiner['body']);

$creatorAfterJoin = request('GET', $game['creatorUrl']);
check('the creator page turns ready', page_attribute($creatorAfterJoin['body'], 'status') === 'ready');
check('the creator is no longer told to wait', stripos($creatorAfterJoin['body'], 'waiting for the opponent') === false);

$revisit = request('GET', $game['playUrl'], null, null, $joinerCookie);
check('a revisit with the cookie is still the joiner', page_attribute($revisit['body'], 'role') === 'joiner');
check('a revisit does not re-issue a seat', set_cookie_header($revisit['headers'], 'joiner') === null);

fwrite(STDOUT, "\nThe joiner seat cannot be stolen\n");
$thirdVisitor = request('GET', $game['playUrl']);
check('a second browser is not handed the seat', set_cookie_header($thirdVisitor['headers'], 'joiner') === null);
check('a second browser is only a spectator', page_attribute($thirdVisitor['body'], 'role') === 'spectator');
check(
    'a second browser is a spectator in the state api too',
    (match_state($baseUrl, $game['matchId'])['json']['you']['role'] ?? null) === 'spectator',
);
check(
    'the original joiner keeps the seat',
    (match_state($baseUrl, $game['matchId'], $joinerCookie)['json']['you']['role'] ?? null) === 'joiner',
);
check(
    'a lost joiner cookie has no recovery path',
    (match_state($baseUrl, $game['matchId'], $joinerCookieName . '=not-the-real-token')['json']['you']['role'] ?? null) === 'spectator',
);

fwrite(STDOUT, "\nThe creator link always wins\n");
check(
    'the creator token beats a joiner cookie',
    (match_state($baseUrl, $game['matchId'], $joinerCookie, $game['creatorToken'])['json']['you']['role'] ?? null) === 'creator',
);
check('the joiner token stays secret', !preg_match('/token_hash|joinerToken/i', $asJoiner['body']), $asJoiner['body']);
check(
    'public state does not contain the raw joiner token',
    $joinerToken !== '' && !str_contains(match_state($baseUrl, $game['matchId'])['body'], $joinerToken),
);

fwrite(STDOUT, "\nNo ply lands before both players are seated\n");
$unseated = create_challenge($baseUrl);
$tooEarly = submit_move($baseUrl, $unseated['matchId'], 'e2e4', null, $unseated['creatorToken']);
check('a move while waiting is refused', $tooEarly['status'] === 409, "got {$tooEarly['status']}");
check('the refusal names the reason', ($tooEarly['json']['error'] ?? null) === 'match_not_started', $tooEarly['body']);

$stillWaiting = match_state($baseUrl, $unseated['matchId'], null, $unseated['creatorToken']);
check('the match is still waiting', ($stillWaiting['json']['status'] ?? null) === 'waiting');
check('no ply was recorded', ($stillWaiting['json']['moveCount'] ?? null) === 0, $stillWaiting['body']);
check('the position is untouched', ($stillWaiting['json']['fen'] ?? null) === START_FEN, $stillWaiting['body']);

fwrite(STDOUT, "\nOnly the seated player to move may move\n");
$game = seated_game($baseUrl);

$bySpectator = submit_move($baseUrl, $game['matchId'], 'e2e4');
check('a spectator cannot move', $bySpectator['status'] === 403, "got {$bySpectator['status']}");
check('the spectator is told they are not a player', ($bySpectator['json']['error'] ?? null) === 'not_a_player', $bySpectator['body']);

$outOfTurn = submit_move($baseUrl, $game['matchId'], 'e7e5', $game['joinerCookie']);
check('black cannot open the game', $outOfTurn['status'] === 409, "got {$outOfTurn['status']}");
check('the out-of-turn refusal says so', ($outOfTurn['json']['error'] ?? null) === 'not_your_turn', $outOfTurn['body']);

$illegal = submit_move($baseUrl, $game['matchId'], 'e2e5', null, $game['creatorToken']);
check('an illegal move is refused with 422', $illegal['status'] === 422, "got {$illegal['status']}");
check('the illegal refusal says so', ($illegal['json']['error'] ?? null) === 'illegal_move', $illegal['body']);
check('the mover is given something readable', is_string($illegal['json']['message'] ?? null) && $illegal['json']['message'] !== '', $illegal['body']);

foreach (['', 'e2', 'e2e9', 'e2e4k', 'e2e2', 'resign'] as $junk) {
    $malformed = submit_move($baseUrl, $game['matchId'], $junk, null, $game['creatorToken']);
    check(
        "'{$junk}' is not a move the board understands",
        $malformed['status'] === 422 && ($malformed['json']['error'] ?? null) === 'malformed_move',
        $malformed['body'],
    );
}

$afterRefusals = match_state($baseUrl, $game['matchId'], $game['joinerCookie']);
check('refused attempts leave the position alone', ($afterRefusals['json']['fen'] ?? null) === START_FEN, $afterRefusals['body']);
check('refused attempts leave the match ready', ($afterRefusals['json']['status'] ?? null) === 'ready');
check('refused attempts append nothing', ($afterRefusals['json']['moveCount'] ?? null) === 0);

fwrite(STDOUT, "\nWhite's first move starts the game\n");
$opening = submit_move($baseUrl, $game['matchId'], 'e2e4', null, $game['creatorToken']);
check('the move is accepted', $opening['status'] === 200, $opening['body']);
check('ready turns into active', ($opening['json']['status'] ?? null) === 'active', $opening['body']);
check('it is now black to move', ($opening['json']['turn'] ?? null) === 'black', $opening['body']);
check(
    'the ply is stored as UCI with a 1-based number',
    last_move($opening['json']) == ['number' => 1, 'uci' => 'e2e4', 'san' => 'e4'],
    json_encode(last_move($opening['json'])),
);
check(
    'the cached FEN followed the move',
    ($opening['json']['fen'] ?? null) === 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
    (string) ($opening['json']['fen'] ?? ''),
);
check('clocks report the full bank after white starts the game', clocks_are_idle_at_full($opening['json']) === false, $opening['body']);
check('black clock is running after the first move', ($opening['json']['clocks']['running'] ?? null) === 'black', $opening['body']);
check(
    'both banks are still essentially full after the first move',
    (int) ($opening['json']['clocks']['white'] ?? 0) >= FULL_BANK_MS - 1000
        && (int) ($opening['json']['clocks']['black'] ?? 0) >= FULL_BANK_MS - 1000,
    $opening['body'],
);
check('result stays empty while the game is active', is_null_field($opening['json'], 'result'), $opening['body']);
check('draw offer stays empty for now', is_null_field($opening['json'], 'drawOffer'), $opening['body']);

fwrite(STDOUT, "\nThe move reaches the opponent and the spectators\n");
$opponentView = match_state($baseUrl, $game['matchId'], $game['joinerCookie']);
check('the opponent sees the new position', ($opponentView['json']['fen'] ?? null) === ($opening['json']['fen'] ?? ''));
check('the opponent sees one ply', ($opponentView['json']['moveCount'] ?? null) === 1, $opponentView['body']);
check('the opponent is the one who may move', ($opponentView['json']['you']['canMove'] ?? null) === true, $opponentView['body']);
check('the opponent is given legal destinations', is_array($opponentView['json']['dests']['e7'] ?? null), $opponentView['body']);

$spectatorView = match_state($baseUrl, $game['matchId']);
check('a spectator sees the position too', ($spectatorView['json']['fen'] ?? null) === ($opening['json']['fen'] ?? ''));
check('a spectator may not move', ($spectatorView['json']['you']['canMove'] ?? null) === false, $spectatorView['body']);
check('a spectator is given no destinations', is_null_field($spectatorView['json'], 'dests'), $spectatorView['body']);
check('a spectator is given no color to play', is_null_field($spectatorView['json']['you'] ?? null, 'color'), $spectatorView['body']);

$moverView = match_state($baseUrl, $game['matchId'], null, $game['creatorToken']);
check('the player who just moved has to wait', ($moverView['json']['you']['canMove'] ?? null) === false, $moverView['body']);
check('the waiting player is given no destinations', is_null_field($moverView['json'], 'dests'), $moverView['body']);
check('each player is told their color', ($moverView['json']['you']['color'] ?? null) === 'white', $moverView['body']);

fwrite(STDOUT, "\nPolling with a cursor\n");
$caughtUp = request('GET', $baseUrl . '/api/matches/' . $game['matchId'] . '?since=1', null, null, $game['joinerCookie']);
check('a caught-up poll still reports the position', ($caughtUp['json']['fen'] ?? null) === ($opening['json']['fen'] ?? ''));
check('a caught-up poll does not resend plies', ($caughtUp['json']['moves'] ?? null) === [], $caughtUp['body']);
check('a caught-up poll still reports the total', ($caughtUp['json']['moveCount'] ?? null) === 1, $caughtUp['body']);
$fromBehind = request('GET', $baseUrl . '/api/matches/' . $game['matchId'] . '?since=0', null, null, $game['joinerCookie']);
check('a poll from behind gets the plies it is missing', count($fromBehind['json']['moves'] ?? []) === 1, $fromBehind['body']);

fwrite(STDOUT, "\nPromotion is the mover's choice\n");
foreach (['q' => 'queen', 'r' => 'rook', 'b' => 'bishop', 'n' => 'knight'] as $piece => $name) {
    $promoting = seated_game($baseUrl);
    check("the line up to the promotion is legal (for the {$name})", play_line($baseUrl, $promoting, PROMOTION_LINE));

    $promoted = submit_move($baseUrl, $promoting['matchId'], 'b7a8' . $piece, null, $promoting['creatorToken']);
    check("promoting to a {$name} is allowed", $promoted['status'] === 200, $promoted['body']);
    check("the {$name} is part of the stored UCI", (last_move($promoted['json'])['uci'] ?? null) === 'b7a8' . $piece, $promoted['body']);
    check("the ply is numbered 9", (last_move($promoted['json'])['number'] ?? null) === 9, $promoted['body']);

    if ($piece === 'n') {
        check(
            'the board shows the chosen piece, not a queen',
            str_starts_with((string) ($promoted['json']['fen'] ?? ''), 'Nnbqkb1r/'),
            (string) ($promoted['json']['fen'] ?? ''),
        );
    }
}

$mustChoose = seated_game($baseUrl);
play_line($baseUrl, $mustChoose, PROMOTION_LINE);
$noChoice = submit_move($baseUrl, $mustChoose['matchId'], 'b7a8', null, $mustChoose['creatorToken']);
check('a promoting move with no piece named is refused', $noChoice['status'] === 422, "got {$noChoice['status']}");
check('the promotion refusal is about legality', ($noChoice['json']['error'] ?? null) === 'illegal_move', $noChoice['body']);
$spuriousChoice = submit_move($baseUrl, $mustChoose['matchId'], 'b7b8q', null, $mustChoose['creatorToken']);
check('naming a piece on a move that cannot promote is refused', $spuriousChoice['status'] === 422, $spuriousChoice['body']);

fwrite(STDOUT, "\nTwo submissions for the same turn cannot both land\n");
$race = seated_game($baseUrl);
$movesUrl = $baseUrl . '/api/matches/' . $race['matchId'] . '/moves?token=' . urlencode($race['creatorToken']);
$raced = post_at_once([
    ['url' => $movesUrl, 'body' => ['uci' => 'e2e4']],
    ['url' => $movesUrl, 'body' => ['uci' => 'd2d4']],
]);
check(
    'exactly one of two simultaneous moves is accepted',
    count(array_filter($raced, static fn (int $status): bool => $status === 200)) === 1,
    implode(', ', $raced),
);
check(
    'only one ply was appended',
    (match_state($baseUrl, $race['matchId'])['json']['moveCount'] ?? null) === 1,
    match_state($baseUrl, $race['matchId'])['body'],
);

fwrite(STDOUT, "\nTimeout ends the game on the server\n");
$timed = seated_game($baseUrl);
$started = submit_move($baseUrl, $timed['matchId'], 'e2e4', null, $timed['creatorToken']);
check('the timed game is active with black to move', ($started['json']['status'] ?? null) === 'active', $started['body']);
if (force_flag($timed['matchId'], 'black')) {
    $flagged = match_state($baseUrl, $timed['matchId'], $timed['joinerCookie']);
    check('a poll flags black on zero time', ($flagged['json']['status'] ?? null) === 'finished', $flagged['body']);
    check('white wins on time', ($flagged['json']['result']['winner'] ?? null) === 'white', $flagged['body']);
    check('the result reason is timeout', ($flagged['json']['result']['reason'] ?? null) === 'timeout', $flagged['body']);

    $tooLate = submit_move($baseUrl, $timed['matchId'], 'e7e5', $timed['joinerCookie']);
    check('a move after the flag is rejected', $tooLate['status'] === 409, "got {$tooLate['status']}");
    check('the rejection says the game is over', ($tooLate['json']['error'] ?? null) === 'match_finished', $tooLate['body']);
} else {
    fwrite(STDOUT, "  skip timeout checks — no local MySQL on port 3307\n");
}

fwrite(STDOUT, "\nThe board on the page\n");
$board = seated_game($baseUrl);

$creatorBoard = request('GET', $board['creatorUrl']);
check('the creator is given a board', page_attribute($creatorBoard['body'], 'fen') === START_FEN, (string) page_attribute($creatorBoard['body'], 'fen'));
check('white sits at the bottom for white', page_attribute($creatorBoard['body'], 'orientation') === 'white');
check('the creator is told they play white', page_attribute($creatorBoard['body'], 'color') === 'white');

$joinerBoard = request('GET', $board['playUrl'], null, null, $board['joinerCookie']);
check('black sits at the bottom for black', page_attribute($joinerBoard['body'], 'orientation') === 'black');
check('the opponent is told they play black', page_attribute($joinerBoard['body'], 'color') === 'black');

$spectatorBoard = request('GET', $board['playUrl']);
check('a spectator sees white at the bottom', page_attribute($spectatorBoard['body'], 'orientation') === 'white');
check('a spectator is handed no color, so the board stays view-only', page_attribute($spectatorBoard['body'], 'color') === '');

check('the board library is served', request('GET', $baseUrl . '/assets/vendor/chessground.min.js')['status'] === 200);
check('the board stylesheet is served', request('GET', $baseUrl . '/assets/vendor/chessground.css')['status'] === 200);

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
check('reading the moves endpoint is rejected', request('GET', $baseUrl . '/api/matches/' . $matchId . '/moves')['status'] === 405);
check('moving in an unknown match is a 404', submit_move($baseUrl, str_repeat('a', 32), 'e2e4')['status'] === 404);
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
