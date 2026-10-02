# Chess Game — Specification

This document supersedes the original briefing. It captures product and implementation decisions agreed in design review.

## Problem Statement

People want a simple way to challenge someone to a casual timed chess game without accounts or apps: create a challenge, share a link, and play in the browser with a familiar board and reliable rules.

## Solution

A small website where a creator configures a challenge (5 or 10 minutes, who plays white), receives two links (one to keep, one to share), and both players meet on a live board. Moves are validated on the server, stored in MySQL, and synchronized via polling. Spectators can watch. Finished games remain viewable as frozen replays on the same URLs.

## User Stories

1. As a creator, I want to choose a time control of 5 or 10 minutes, so that the game matches how long we have to play.
2. As a creator, I want to choose whether I or my opponent plays white, so that colors are decided before we start.
3. As a creator, I want to create a challenge without signing up, so that I can start a game quickly.
4. As a creator, I want a private link that always identifies me as the creator, so that I cannot lose my seat by mistake.
5. As a creator, I want a separate link to copy for my opponent, so that I do not accidentally share my secret creator token.
6. As a creator, I want to see clear labels for “your link” and “opponent link” after creation, so that I know what to save vs share.
7. As an opponent, I want to open the shared link and join the game automatically, so that I do not need instructions or login.
8. As an opponent, I want my browser to remember that I am the joiner, so that refresh keeps my role.
9. As a creator, I want to reopen my saved creator link and still be the creator, so that I do not depend on cookies for my role.
10. As either player, I want to see “waiting for opponent” until the joiner arrives, so that I know the challenge is not live yet.
11. As either player, I want no moves allowed until both players are present, so that neither side gets a hidden head start.
12. As either player, I want the clock not to run while waiting or before play starts, so that time is fair.
13. As either player, I want the clock to start only after white’s first move, so that we are both ready before time pressure begins.
14. As either player, I want to see my remaining time and my opponent’s time update during the game, so that I can manage the clock.
15. As either player, I want the server to enforce timeouts, so that flagging is objective.
16. As either player, I want to move pieces on a 2D board viewed from above (chess.com–style), so that the UI feels familiar.
17. As a player, I want the board oriented with my pieces toward the bottom, so that reading the position is natural.
18. As a player, I want to move only on my turn and only with legal moves, so that rules are enforced consistently.
19. As a player, I want illegal move attempts rejected with clear feedback, so that I understand why a move failed.
20. As a player, I want to promote a pawn by choosing queen, rook, bishop, or knight when required, so that under-promotion is supported.
21. As either player, I want the board and clocks to update within about a second of my opponent’s actions, so that the game feels live without complex infrastructure.
22. As either player, I want checkmate, stalemate, and insufficient material to end the game automatically, so that standard endings are handled.
23. As either player, I want to resign, so that I can concede when the position is hopeless.
24. As either player, I want to offer a draw and accept or decline my opponent’s offer, so that we can agree to a draw.
25. As either player, I want to see the game result when it ends, so that we know who won or if it was a draw.
26. As a spectator, I want to open the public game link after two players are seated and watch the game, so that friends can follow along.
27. As a spectator, I want a fixed board orientation (white on bottom), so that a shared screen is readable.
28. As a spectator, I want no ability to move pieces or affect the game, so that the match stays integrity-preserving.
29. As anyone with the link, I want to view a finished game as a read-only replay, so that we can review the game later.
30. As a creator, I want unjoined challenges to expire after one hour, so that stale links do not clutter the system forever.
31. As anyone opening an expired unjoined challenge, I want a clear “challenge expired” message, so that I know to ask for a new link.
32. As a developer, I want to run the full stack locally with Docker Compose, so that I can develop without manual PHP/MySQL setup.
33. As a deployer, I want production to run on traditional Apache PHP hosting with MySQL, so that deployment matches my VPS setup.
34. As a deployer, I want pretty URLs via Apache rewrite rules, so that links are clean and shareable.

## Implementation Decisions

### Architecture and stack

- **Backend:** Plain PHP 8.x, no application framework. Composer allowed for a chess rules/validation library and autoloading only.
- **Database:** MySQL. Persist matches, moves, tokens (hashed), clock state, and game status.
- **Frontend:** Vanilla JavaScript for polling and API glue; **Chessground** for board rendering and interaction (client is not authoritative for legality).
- **Local dev:** Docker Compose (nginx + php-fpm + MySQL), with nginx rules for routing during development.
- **Production:** Apache with document root at the public entrypoint; **`mod_rewrite` via `.htaccess`** forwarding non-file requests to a single front controller. Environment-based configuration (DSN, secrets); no secrets in version control. Deploy includes Composer `vendor` on the host (no Docker in production).

### Identity and URLs

- **Match id:** Opaque, cryptographically random (e.g. UUID v4 or similar URL-safe string)—not sequential integers.
- **Two links per challenge:**
  - **Play link (share):** `/game/{matchId}` — opponent and spectators; no creator secret.
  - **Creator link:** `/game/{matchId}?token=…` — maps always to creator role; token stored hashed server-side.
- **Joiner binding:** First visitor to the play link without a valid creator token claims joiner; store **`joiner_token_hash`** and set an **httpOnly cookie** with the raw joiner token. Revisits with that cookie remain joiner. No joiner recovery if the cookie is lost (user becomes spectator only). Second joiner attempt does not replace the joiner.
- **Role resolution:** API uses creator token query param when present; else joiner cookie; else spectator (or joiner-claim flow on first eligible visit).

### Game lifecycle and state machine

```
waiting     → no joiner yet (clocks idle; no moves)
abandoned   → waiting past expiry (1 hour since creation, no joiner)
ready       → joiner present; clocks idle; no moves until white moves
active      → after white’s first accepted move; clocks running per server rules
finished    → terminal (result recorded; read-only replay)
```

- **Presence rule:** No moves until status is at least `ready` (joiner locked). Clock does **not** start on join; it starts when the first **white** move is accepted (`ready` → `active`).
- **Waiting expiry:** If still `waiting` after **1 hour** from challenge creation, transition to `abandoned`. Enforce **lazily** on state reads (poll, page load)—no cron required for v1.
- **Finished games:** Same URLs show frozen replay (board, result, move list); no new moves, resign, or draw actions.

### Time control

- Options at create: **5 minutes** or **10 minutes** per player (single period, no increment in v1).
- **Server-authoritative clocks:** Store remaining time per side (milliseconds or equivalent precision) and `turn_started_at` (or equivalent). On each accepted move, deduct elapsed time from the mover’s bank, switch turn, reset turn start. On poll/state read, compute display times and detect timeout.
- **Timeout:** Side to move loses when remaining time ≤ 0.

### Moves and rules

- **Storage:** Each move row: match id, **move number** (1-based ply index), **UCI** move key (`from`, `to`, optional promotion character). Do not use SAN as source of truth in the database.
- **Validation:** Server validates every submitted move against current position (from move list or cached FEN): legal move, correct side, game not terminal, status allows moves, clock rules satisfied.
- **Display:** Optionally derive SAN for UI move list from the rules library when serving state.
- **Position cache:** Maintain current **FEN** on the match row updated on each accepted move for efficient validation and polling responses.

### Real-time sync

- **Polling interval:** ~**1 second** while the client is on an in-progress game page.
- **Poll contract (conceptual):** Client sends last known move number (or version); server returns full public game state: status, FEN, clocks, move list, whose turn, terminal result if any, draw-offer state, and caller’s role (creator / joiner / spectator).

### Game termination (v1)

- **Automatic:** Checkmate, stalemate, insufficient material (via rules engine after each move).
- **Timeout:** As above.
- **Resign:** Authenticated role (creator or joiner) only.
- **Draw:** Offer stored on match; opponent accepts → draw. Decline or new move clears offer as appropriate.
- **Not in v1:** Threefold/fifty-move **claims** as separate UI actions (unless the engine auto-declares without a claim button—optional, not required).

### Board UX

- **Players:** Chessground configured with orientation so **own color is at the bottom**.
- **Spectators:** White at bottom, black at top; no move handlers.
- **Interaction:** Drag/drop (and tap if supported); promotion picker when needed; legal move hints may come from server or optimistic UI with server rejection on illegal attempts.

### API surface (conceptual)

- Create match (time, creator plays white or not) → returns match id, creator URL, play URL.
- Get/join state for match id ( establishes joiner on first eligible visit ).
- Poll state (with cursor/version).
- Submit move (UCI).
- Resign, offer draw, accept/decline draw.

All mutation endpoints enforce role and game status.

### Database (conceptual entities)

- **Match:** id, created_at, time control, which role plays white (creator vs joiner), status, FEN, clock fields, creator token hash, joiner token hash, draw offer metadata, result fields when finished.
- **Move:** match id, move number, UCI string, created_at (optional).

### Testing seam (highest level)

Prefer **one primary seam:** the **HTTP JSON API** against a real MySQL instance (local Docker), treating the app as a black box: create challenge → join as opponent → play through checkmate, timeout, resign, draw, expiry, and spectator read-only access. This validates lifecycle, clocks, persistence, and rules without coupling to internal PHP structure.

## Testing Decisions

- **No automated unit tests** in v1 (explicit product choice).
- **Good manual / optional integration checks** exercise **external behavior only:** HTTP status codes, JSON shapes, role enforcement, illegal moves rejected, clocks and status transitions, expiry after one hour in `waiting`, and replay immutability after `finished`.
- **Prior art:** Greenfield; no existing test patterns in the repo.

## Out of Scope

- User accounts, authentication, ratings, or matchmaking.
- WebSockets, SSE, or sub-second live updates.
- Takebacks, rematch, in-game chat, PGN export/download.
- Joiner role recovery without the original cookie.
- Mobile-native apps.
- Production TLS/hosting automation (beyond documenting Apache docroot and env vars).
- Docker-based production deployment.
- PHP frameworks (Laravel, Symfony, etc.).
- React/Vue SPA architecture.
- Automated unit/integration test suite in the initial delivery (optional manual API checks only).

## Further Notes

- **npm / package.json:** May vend Chessground (and minimal bundling or static copy into public assets) separately from PHP; keep production deploy simple (built or vendored static JS/CSS on the VPS).
- **Security:** Treat creator and joiner tokens as secrets; hash at rest; use HTTPS in production so query tokens and cookies are protected.
- **Concurrency:** Use transactions or row-level locking when appending moves and updating clocks so double submissions cannot corrupt state.
- Original briefing intent preserved: minimal create form (time + white choice), share URL, auto-start when opponent arrives, moves recorded by match id with standard move encoding (UCI), chess.com-like top-down board.
