#!/usr/bin/env bash
# Assembles a self-contained tree in dist/ for manual upload (FTP, rsync, etc.).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ROOT}/dist"
ENV_PRODUCTION="${ROOT}/.env.production"

if [[ ! -f "${ENV_PRODUCTION}" ]]; then
  echo "Missing ${ENV_PRODUCTION}. Copy .env.example and fill in production values." >&2
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "Composer is not on PATH. Install it locally, or run:" >&2
  echo "  docker compose exec php composer install --no-dev --optimize-autoloader" >&2
  echo "then re-run this script (vendor/ must exist under the project root)." >&2
  exit 1
fi

echo "Building production bundle in dist/ ..."

rm -rf "${DIST}"
mkdir -p "${DIST}"

cp "${ROOT}/composer.json" "${ROOT}/composer.lock" "${DIST}/"
cp -R "${ROOT}/public" "${ROOT}/src" "${ROOT}/templates" "${ROOT}/db" "${ROOT}/scripts" "${DIST}/"
cp "${ENV_PRODUCTION}" "${DIST}/.env"

(
  cd "${DIST}"
  composer install --no-dev --optimize-autoloader --no-interaction
)

echo ""
echo "Done. Upload the contents of dist/ to your server (not the dist folder itself)."
echo "Point the web document root at public/ inside that tree."
echo "After the first deploy, run: php scripts/migrate.php (SSH or host panel)."
