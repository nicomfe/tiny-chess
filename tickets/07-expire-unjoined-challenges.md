# Expire unjoined challenges after one hour

**What to build:** If nobody joins within an hour of creation, the challenge is abandoned. Anyone who opens those links sees a clear “challenge expired” message instead of a board. Joined and finished games are not expired this way.

**Blocked by:** Join as opponent and wait until both are seated

- [ ] A match still in `waiting` one hour after creation becomes `abandoned` when state is read (poll or page load) — lazy, no cron required.
- [ ] Opening an abandoned challenge shows a clear “challenge expired” message; the visitor knows to ask for a new link.
- [ ] No joiner can be claimed and no moves can be played on an abandoned challenge.
- [ ] Matches that already reached `ready`, `active`, or `finished` are not transitioned to `abandoned` by the one-hour rule.
