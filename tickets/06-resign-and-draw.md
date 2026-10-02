# Resign and agree a draw

**What to build:** A seated player can resign and lose. Either player can offer a draw; the opponent can accept (game drawn) or decline. A new move clears an outstanding offer as appropriate. Spectators cannot resign or negotiate draws. The result shows on the same frozen replay as other finished games.

**Blocked by:** End games automatically and show frozen replay

- [ ] Only creator or joiner can resign, and only while the game is in progress (`ready` or `active`); the opponent wins and status becomes `finished`.
- [ ] Either seated player can offer a draw; the offer is stored on the match and visible in polled state.
- [ ] The opponent can accept (draw, `finished`) or decline (offer cleared, play continues).
- [ ] A new accepted move clears an outstanding draw offer as appropriate.
- [ ] Spectators cannot resign, offer, accept, or decline.
- [ ] After resign or agreed draw, the URLs show the frozen replay and result; further moves and draw/resign actions are rejected.
