# Tiny Chess

Create a timed chess challenge without an account, share one link, and play in the browser.


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

## Deploy to production

Production is Apache (or compatible) PHP + MySQL on your VPS or shared host — not Docker. You
build on your machine and upload the bundle with FileZilla (or any FTP/SFTP client).

### One-time setup

1. Copy `.env.example` to `.env.production` and set production values:
   - `APP_SECRET` — generate once with `openssl rand -hex 32` and **never change** after go-live
     (changing it invalidates every creator link).
   - `APP_BASE_URL` — your public origin, e.g. `https://chess.example.com` (no trailing slash).
   - `DB_*` — MySQL credentials from your host (often `DB_HOST=localhost`).
2. Create an empty MySQL database and user on the host; grant the user full access to that database.

### Build the upload bundle

On your Mac (PHP 8.2+ and [Composer](https://getcomposer.org/) on PATH):

```bash
chmod +x scripts/build-prod.sh   # once
./scripts/build-prod.sh
```

This writes a fresh tree under `dist/`:

- Application code, templates, migrations, and `public/` (including vendored Chessground assets).
- `vendor/` from `composer install --no-dev --optimize-autoloader`.
- `.env` copied from your local `.env.production` (secrets stay out of git).

If Composer is only available inside Docker locally:

```bash
docker compose exec php composer install --no-dev --optimize-autoloader
./scripts/build-prod.sh
```

Optional: after bumping the `chessground` npm dependency, run `npm install && npm run vendor`
**before** `./scripts/build-prod.sh` so `public/assets/vendor/` is up to date.

### Upload with FTP to your server

1. Choose a directory **outside** the web-visible tree if you can (e.g. `~/chess-game/`). Upload
   **everything inside** `dist/` — `public/`, `src/`, `vendor/`, `.env`, and the rest — not the
   `dist` folder name itself.
2. Point the site **document root** at the `public/` folder inside that upload (cPanel “Document
   Root”, Plesk “Hosting settings”, or your provider’s equivalent). Pretty URLs rely on
   `public/.htaccess` and `mod_rewrite`.
3. PHP must be **8.2 or newer** with PDO MySQL enabled (typical on managed PHP hosting).

Do **not** upload `.env.production`, `.git/`, `docker/`, or `node_modules/`. The build script already
omits those.

### Database migrations

After the first upload (and after any deploy that adds new files under `db/migrations/`), apply
pending migrations once.

**With shell + PHP** (ideal):

```bash
cd /path/to/your/upload
php scripts/migrate.php
```

If you only have a control-panel “Run PHP script” or cron, run the same command there against the
upload root (the directory that contains `vendor/` and `.env`).

**SQL only** (phpMyAdmin, Adminer, host “Run SQL”, etc.) — use this when the host does not let you
run CLI PHP. The app ships the same files under `db/migrations/` in your upload; run them **in
numeric order** (`001_…` through `007_…` today). Skip any file whose effects are already in the
database (e.g. `matches` already exists → start at `002_…`).

1. Create the migration tracker (once per database):

```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(255) NOT NULL,
    applied_at DATETIME(3) NOT NULL,
    PRIMARY KEY (filename)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```

2. For each migration file you execute, paste its full contents into the SQL runner, run it, then
   record it (repeat for every new file):

```sql
INSERT INTO schema_migrations (filename, applied_at)
VALUES ('001_create_matches.sql', UTC_TIMESTAMP(3));
```

Use the real basename for each file (`002_add_joiner_seat.sql`, …). To see what is already
applied: `SELECT filename FROM schema_migrations ORDER BY filename`.

On later deploys, run only migrations that are **not** listed there, then `INSERT` one row per new
file. That matches what `scripts/migrate.php` would do, so you can switch to PHP later without
double-applying.

### Smoke test

Open `APP_BASE_URL` in a browser: create a challenge, open both links, play a move.

If you have shell access and PHP on the host:

```bash
php scripts/api-check.php https://your-domain.example
```

Use your host’s error log when something fails; API responses intentionally hide details.


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
without cookies. The joiner seat goes to the first browser that opens the play link and completes the join step
(`POST /api/matches/{id}/join` from the page script), and is then locked: the match becomes
`ready`, a second browser only ever watches, and a browser that loses its cookie has no way back
in. Every state response reports the caller's role under `you.role`.

The play page itself only resolves an existing role. Joining is a separate POST so link previews
that fetch the shared URL cannot take the seat; the state API also never hands one out.

## Playing

| Endpoint                            | What it does                                            |
| ----------------------------------- | ------------------------------------------------------- |
| `GET /api/matches/{id}?since={n}`   | Public state; `n` is the newest ply the caller has       |
| `POST /api/matches/{id}/join`       | Claims the open joiner seat for this browser             |
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
