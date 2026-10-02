# Play legal moves on a live board

**What to build:** Once both players are seated, they play on a chess.com-style 2D board. Moves are legal only on your turn, validated on the server, stored as UCI, and appear on the opponent’s (and spectator’s) board within about a second. Illegal attempts are rejected with clear feedback. Promotion allows queen, rook, bishop, or knight. Spectators can watch but cannot move.

**Blocked by:** Join as opponent and wait until both are seated

- [ ] No move is accepted while status is `waiting`; both players must be seated (`ready` or later) before a ply can land.
- [ ] The board is a top-down 2D board (Chessground); each player’s own color is at the bottom; spectators see white at the bottom.
- [ ] Only the seated player whose turn it is can submit a move; spectators have no move handlers and cannot affect the match.
- [ ] Every submitted move is server-validated (legal in the position, correct side, status allows moves) and stored as UCI with a 1-based move number; current FEN is cached on the match.
- [ ] White’s first accepted move transitions `ready` → `active`.
- [ ] Concurrent double-submits cannot append two moves for the same turn (transaction or row lock).
- [ ] Illegal or out-of-turn attempts are rejected with clear feedback to the mover; the position does not change.
- [ ] When a pawn must promote, the player chooses queen, rook, bishop, or knight; the chosen piece is part of the stored UCI.
- [ ] Clients poll about once per second and send a cursor (last known move number or version); the response includes public state: status, FEN, move list, whose turn, caller’s role (and clocks/result/draw fields as empty or unused until later tickets).
- [ ] The opponent’s and a spectator’s boards update within about a second of an accepted move.
