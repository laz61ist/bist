#!/usr/bin/env bash
# Kokpit E2E kosucusu: temiz sqlite, sunucuyu baslat, testi kos, temizle.
#
#   ./tests/E2E/calistir.sh
set -euo pipefail

KOK="$(cd "$(dirname "$0")/../.." && pwd)"
export E2E_DB_PATH="${E2E_DB_PATH:-$(mktemp -u /tmp/bist-e2e-XXXXXX.sqlite)}"
export E2E_SHOT_DIR="${E2E_SHOT_DIR:-$KOK/var/ekran}"
PORT="${E2E_PORT:-8200}"

mkdir -p "$E2E_SHOT_DIR"
rm -f "$E2E_DB_PATH"

php -S "127.0.0.1:${PORT}" -t "$KOK/public" "$KOK/tests/E2E/AppServerRouter.php" \
  > "${E2E_SHOT_DIR}/php.log" 2>&1 &
SUNUCU=$!
temizle() { kill "$SUNUCU" 2>/dev/null || true; rm -f "$E2E_DB_PATH"; }
trap temizle EXIT

for _ in $(seq 1 40); do
  curl -sf --noproxy '*' "http://127.0.0.1:${PORT}/saglik" >/dev/null 2>&1 && break
  sleep 0.25
done

E2E_BASE="http://127.0.0.1:${PORT}" node "$KOK/tests/E2E/kokpit.e2e.mjs"
