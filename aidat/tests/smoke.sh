#!/usr/bin/env bash
# Duman testi: giriş yapar, tüm GET rotalarını tarar; 200/302 dışını ve PHP uyarılarını raporlar.
# Kullanım: tests/smoke.sh [baseUrl] [email] [password]
set -u
BASE="${1:-http://127.0.0.1:8090}"
EMAIL="${2:-demo.yonetici@aidat.local}"
PASS="${3:-Demo1234!}"
CJ="$(mktemp)"; OUT="$(mktemp)"
curl -s -c "$CJ" -b "$CJ" -o "$OUT" "$BASE/giris"
TOKEN=$(grep -o 'name="_token" value="[^"]*"' "$OUT" | head -1 | sed 's/.*value="//;s/"//')
curl -s -c "$CJ" -b "$CJ" -o /dev/null -X POST --data-urlencode "_token=$TOKEN" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" "$BASE/giris"
FAIL=0; N=0
while IFS= read -r path; do
  N=$((N+1))
  code=$(curl -s -b "$CJ" -o "$OUT" -w '%{http_code}' "$BASE$path")
  warn=$(grep -c -E '<b>(Warning|Notice|Deprecated|Fatal error)</b>|Undefined (array key|variable|index)' "$OUT" || true)
  if [[ "$code" != "200" && "$code" != "302" ]] || [[ "$warn" != "0" ]]; then
    echo "FAIL $code warn=$warn $path"; FAIL=$((FAIL+1))
  else
    echo "ok   $code $path"
  fi
done < <(php "$(dirname "$0")/routes-get.php")
echo "---- $N rota, $FAIL hata"
rm -f "$CJ" "$OUT"
[[ "$FAIL" == "0" ]]
