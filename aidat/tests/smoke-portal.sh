#!/usr/bin/env bash
# Sakin alanı duman testi: portal rotaları + yönetim alanına erişimin reddedildiğini doğrular.
set -u
BASE="${1:-http://127.0.0.1:8090}"; EMAIL="${2:-demo.malik@aidat.local}"; PASS="${3:-Demo1234!}"
CJ="$(mktemp)"; OUT="$(mktemp)"
curl -s -c "$CJ" -b "$CJ" -o "$OUT" "$BASE/giris"
TOKEN=$(grep -o 'name="_token" value="[^"]*"' "$OUT" | head -1 | sed 's/.*value="//;s/"//')
curl -s -c "$CJ" -b "$CJ" -o /dev/null -X POST --data-urlencode "_token=$TOKEN" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" "$BASE/giris"
FAIL=0
for p in /sakin /sakin/borclarim /sakin/odemelerim /sakin/ekstre /sakin/mali-durum /sakin/giderler /sakin/butce /sakin/borclular /sakin/duyurular /sakin/duyurular/1 /sakin/toplantilar /sakin/anketler /sakin/talepler /sakin/talepler/yeni /sakin/belgeler /sakin/sayaclar /profil; do
  code=$(curl -s -b "$CJ" -o "$OUT" -w '%{http_code}' "$BASE$p")
  warn=$(grep -c -E '<b>(Warning|Notice|Deprecated|Fatal error)</b>|Undefined (array key|variable|index)' "$OUT" || true)
  if [[ "$code" != "200" && "$code" != "302" ]] || [[ "$warn" != "0" ]]; then echo "FAIL $code warn=$warn $p"; FAIL=$((FAIL+1)); else echo "ok   $code $p"; fi
done
# Yönetim alanı sakine kapalı olmalı (302 portala yönlendirme veya 403)
code=$(curl -s -b "$CJ" -o /dev/null -w '%{http_code}' "$BASE/yonetim/tahsilatlar")
if [[ "$code" == "200" ]]; then echo "FAIL yönetim alanı sakine açık!"; FAIL=$((FAIL+1)); else echo "ok   $code /yonetim/tahsilatlar (sakin erişemez)"; fi
echo "---- $FAIL hata"; rm -f "$CJ" "$OUT"; [[ "$FAIL" == "0" ]]
