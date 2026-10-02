# Create a challenge and receive two links

**What to build:** A visitor can create a casual chess challenge with no account: pick 5 or 10 minutes and who plays white, then see two clearly labeled links — one private creator link to keep, one play link to share. The stack runs locally with Docker Compose. Opening the creator link later still identifies them as the creator.

**Blocked by:** None — can start immediately.

- [ ] Local Docker Compose brings up the web app and MySQL so a developer can create a challenge without installing PHP or MySQL by hand.
- [ ] The create form offers only 5 or 10 minutes per player and whether the creator or the opponent plays white.
- [ ] Submitting the form creates a match with an opaque, non-sequential id and persists time control, color assignment, hashed creator token, and `waiting` status.
- [ ] After create, the page labels **your link** (creator URL with secret token) vs **opponent link** (play URL with match id only).
- [ ] The creator token is treated as a secret: stored hashed, never shown in logs or public match state, and the play link does not contain it.
- [ ] Reopening the saved creator link still maps to the creator role without relying on cookies.
- [ ] Pretty URLs work in local development (match pages are `/game/{matchId}`, not raw script paths).
- [ ] Configuration (DSN, secrets) comes from the environment; no secrets are committed.
