# Join as opponent and wait until both are seated

**What to build:** The opponent opens the shared play link and is seated as joiner automatically. Until they arrive, both sides see that the challenge is waiting. Once they join, the match is `ready` and no one else can steal the joiner seat. A third visitor becomes a spectator. Refresh keeps each person’s role.

**Blocked by:** Create a challenge and receive two links

- [ ] The first visitor to the play link without a valid creator token is claimed as joiner: a joiner token is hashed server-side and the raw token is stored in an httpOnly cookie.
- [ ] A later visit with that cookie still resolves as joiner; a second browser without the cookie does not replace the joiner and is treated as spectator.
- [ ] The creator link (token query param) always wins over cookies and never claims or yields the joiner seat.
- [ ] While status is `waiting`, both the creator and anyone on the play link see a clear “waiting for opponent” state.
- [ ] When the joiner is claimed, status becomes `ready` (clocks still idle; play has not started).
- [ ] Losing the joiner cookie has no recovery path: that browser is spectator-only, matching the spec.
- [ ] Role is visible in the state API as creator, joiner, or spectator so the UI can speak correctly.
