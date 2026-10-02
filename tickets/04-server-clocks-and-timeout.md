# Run server clocks and flag timeouts

**What to build:** Each side has a 5- or 10-minute bank that stays frozen while waiting and after join. The clock starts only when white’s first move is accepted. During play both remaining times update from the server. If the side to move hits zero, they lose on time and the game is finished.

**Blocked by:** Play legal moves on a live board

- [ ] Clocks do not run in `waiting` or `ready`; remaining time for each side equals the chosen control until white’s first accepted move.
- [ ] On that first white move, clocks start (`ready` → `active` if not already) and the server becomes authoritative for remaining time and whose clock is ticking.
- [ ] After each accepted move, elapsed time is deducted from the mover’s bank, the turn switches, and the turn timer resets.
- [ ] Poll and page load return displayable remaining times for both sides so both players can manage the clock.
- [ ] If remaining time for the side to move is ≤ 0 on a state read or move attempt, that side loses on timeout, status becomes `finished`, and a result is recorded.
- [ ] A move submitted after the mover has flagged is rejected; the timeout result stands.
