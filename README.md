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

`APP_SECRET` keys the player token hashes: change it and every existing creator link stops
resolving, so generate it once per environment and keep it out of version control.

## Check it works

```bash
docker compose exec php php scripts/api-check.php http://web
```

This drives the HTTP API as a black box — creating challenges, rejecting invalid options,
resolving the creator role from a saved link, and confirming the creator token never appears in
public responses. Per the specification there is no automated unit test suite; this script is the
manual check. Run it against any origin, e.g. `php scripts/api-check.php http://localhost:8080`
if you do have PHP on the host.

Responses never include error detail; when something breaks, read the log:

```bash
docker compose logs -f php
```

## Layout

| Path               | What lives there                                                 |
| ------------------ | ---------------------------------------------------------------- |
| `public/`          | Document root: front controller plus static assets               |
| `src/`             | Application code, autoloaded as `Chess\` (PSR-4)                 |
| `src/Game/`        | Match domain: ids, time control, roles, challenge creation       |
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
