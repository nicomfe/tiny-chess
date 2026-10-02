# Run production on Apache with pretty URLs

**What to build:** The same app can be deployed on traditional Apache PHP hosting with MySQL: document root at the public entrypoint, `.htaccess` rewrite to a single front controller, environment-based config, Composer `vendor` on the host, and static board assets already built or vendored. Local Docker remains the dev story; production is not Docker.

**Blocked by:** Create a challenge and receive two links

- [ ] Apache `mod_rewrite` via `.htaccess` forwards non-file requests to the front controller so `/game/{matchId}` and creator-token links work on the VPS.
- [ ] Document root is the public entrypoint; PHP and MySQL configuration come from environment variables (DSN, secrets), not committed files.
- [ ] Deploy instructions cover installing Composer dependencies on the host and serving built or vendored JS/CSS (Chessground) without a Node server in production.
- [ ] Production is documented as Apache + PHP + MySQL, not Docker; TLS/hosting automation stays out of scope beyond docroot and env vars.
