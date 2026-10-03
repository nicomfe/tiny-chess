# Casual Chess

Create a timed chess challenge without an account, share one link, and play in the browser.
See [briefing.md](briefing.md) for the specification and [tickets/](tickets) for the delivery plan.

## Run it locally

Docker Compose brings up nginx, php-fpm and MySQL, so no local PHP or MySQL install is needed.

```bash
cp .env.example .env
printf 'APP_SECRET=%s\n' "$(openssl rand -hex 32)" >> .env

docker compose up -d --build
docker compose exec php composer install
docker compose exec php php scripts/migrate.php
```

The app is then on <http://localhost:8080>.

The board library is vendored into `public/assets/vendor/` and committed, so neither Docker nor
npm is needed to serve it. Refresh it after bumping Chessground:

```bash
npm install && npm run vendor
```

`APP_SECRET` keys the player token hashes: change it and every existing creator link stops
resolving, so generate it once per environment and keep it out of version control.

## Check it works

```bash
docker compose exec php php scripts/api-check.php http://web
```

This drives the HTTP API as a black box — creating challenges, rejecting invalid options,
resolving the creator role from a saved link, confirming the creator token never appears in public
responses, and playing plies through legality, turn order, promotion and simultaneous submissions.
Per the specification there is no automated unit test suite; this script is the manual check. Run
it against any origin, e.g. `php scripts/api-check.php http://localhost:8080` if you do have PHP on
the host.

Responses never include error detail; when something breaks, read the log:

```bash
docker compose logs -f php
```

## Layout

| Path               | What lives there                                                 |
| ------------------ | ---------------------------------------------------------------- |
| `public/`          | Document root: front controller plus static assets               |
| `src/`             | Application code, autoloaded as `Chess\` (PSR-4)                 |
| `src/Game/`        | Match domain: ids, time control, creation, seating, moves        |
| `templates/`       | Plain PHP views                                                  |
| `db/migrations/`   | Numbered SQL migrations, applied by `scripts/migrate.php`        |
| `docker/`          | Local nginx and php-fpm images                                   |

Pretty URLs are handled by nginx in development (`try_files $uri /index.php`). The equivalent
Apache `.htaccess` rewrite arrives with the production hosting ticket.

## URLs

| URL                       | Who it is for                                               |
| ------------------------- | ----------------------------------------------------------- |
| `/`                       | Create a challenge                                          |
| `/game/{matchId}`         | The shareable play link — opponent and spectators           |
| `/game/{matchId}?token=…` | The creator's private link; the token proves creator role   |

## Roles

Nobody signs in, so a role is whatever credential the request happens to carry:

| Role        | How it is proved                                                           |
| ----------- | -------------------------------------------------------------------------- |
| `creator`   | The `token` query param on the creator link, checked against a stored hash  |
| `joiner`    | An httpOnly cookie, set when that browser first opened the play link        |
| `spectator` | Anyone else                                                                |

The creator token always wins, which is what lets a saved creator link work on any device and
without cookies. The joiner seat goes to the first play-link visitor who is not the creator, and is
then locked: the match becomes `ready`, a second browser only ever watches, and a browser that
loses its cookie has no way back in. Every state response reports the caller's role under
`you.role`.

Only opening the play page can claim a seat — the state API reads roles but never hands one out, so
a background poll cannot seat anyone by accident.

## Playing

| Endpoint                            | What it does                                            |
| ----------------------------------- | ------------------------------------------------------- |
| `GET /api/matches/{id}?since={n}`   | Public state; `n` is the newest ply the caller has       |
| `POST /api/matches/{id}/moves`      | Submits one move as `{"uci": "e2e4"}`                    |

The client is never authoritative. `src/Game/Position.php` is the only code that knows the rules,
and every submission is checked against the stored FEN under a row lock: both seats filled, game
not over, the mover's turn, and the move legal — including that a promotion piece is named exactly
when the move calls for one. A refused move changes nothing and comes back with a reason the mover
is shown (`not_a_player`, `not_your_turn`, `match_not_started`, `illegal_move`, …).

Plies are stored as UCI with a 1-based move number, keyed `(match_id, move_number)` so two
submissions racing for one turn cannot both land; SAN is stored alongside only so the move list can
be shown without replaying the game. The match row carries the current FEN, and white's first
accepted move is what turns `ready` into `active`.

Boards poll once a second with the newest ply they know, and a response carries only the plies
after it. The board is drawn from the server's FEN rather than from the move list, so it cannot
drift. Players get a `dests` map of legal moves for their own turn and nothing otherwise, which is
also what leaves a spectator's board with nothing to drag.
