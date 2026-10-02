# Tickets: Casual timed chess

A small website where someone creates a timed challenge, shares a link, and both players meet on a live board — from [briefing.md](../briefing.md).

Work the **frontier**: any ticket whose blockers are all done. For a purely linear chain that means lowest number first; tickets 07 and 08 can start as soon as their blockers land, even if later play tickets are unfinished.

| # | Ticket | Blocked by |
|---|--------|------------|
| 01 | [Create a challenge and receive two links](01-create-challenge-and-two-links.md) | None |
| 02 | [Join as opponent and wait until both are seated](02-join-and-wait-until-both-are-seated.md) | 01 |
| 03 | [Play legal moves on a live board](03-play-legal-moves-on-live-board.md) | 02 |
| 04 | [Run server clocks and flag timeouts](04-server-clocks-and-timeout.md) | 03 |
| 05 | [End games automatically and show frozen replay](05-automatic-endings-and-replay.md) | 03 |
| 06 | [Resign and agree a draw](06-resign-and-draw.md) | 05 |
| 07 | [Expire unjoined challenges after one hour](07-expire-unjoined-challenges.md) | 02 |
| 08 | [Run production on Apache with pretty URLs](08-apache-production-hosting.md) | 01 |
