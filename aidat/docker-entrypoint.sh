#!/bin/sh
set -e
cd /var/www/html
php bin/aidat migrate >/dev/null
if [ "${AIDAT_DEMO:-0}" = "1" ]; then php bin/aidat seed >/dev/null || true; fi
chown -R www-data:www-data storage
exec "$@"
