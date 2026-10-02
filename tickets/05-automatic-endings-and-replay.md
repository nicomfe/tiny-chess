# End games automatically and show frozen replay

**What to build:** Checkmate, stalemate, and insufficient material end the game without extra clicks. Everyone on the match URLs sees who won or that it was a draw. After that, the same links are a read-only replay: board, result, and move list frozen; no new moves or game actions.

**Blocked by:** Play legal moves on a live board

- [ ] After each accepted move, the rules engine detects checkmate, stalemate, and insufficient material and transitions the match to `finished` with the correct result.
- [ ] Players and spectators see a clear result (win for a side, or draw) when the game ends.
- [ ] Finished matches remain reachable on the same creator and play URLs as a frozen replay: last position, move list, and result.
- [ ] Replay is read-only: move submit, resign, and draw actions are rejected; spectators still cannot act.
- [ ] Timeout (and later resign/draw) finished games also render as this same frozen replay once those tickets land; this ticket owns the `finished` + replay behaviour for automatic endings first.
- [ ] Optional SAN in the move list may be derived for display; UCI in storage remains source of truth.
