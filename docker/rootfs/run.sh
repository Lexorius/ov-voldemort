#!/bin/sh
# Startskript des Containers: einrichten, php-fpm starten, nginx in den Vordergrund.
set -e

echo "[run] OV-Budget startet ..."

mkdir -p /data/uploads /data/sessions /run/nginx
chown -R www-data:www-data /data/uploads /data/sessions
chmod 750 /data/uploads /data/sessions

# Zeitzone übernehmen, falls Home Assistant sie mitgibt
if [ -n "${TZ:-}" ] && [ -f "/usr/share/zoneinfo/${TZ}" ]; then
    cp "/usr/share/zoneinfo/${TZ}" /etc/localtime
    echo "${TZ}" > /etc/timezone
    echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/zz-timezone.ini
    echo "[run] Zeitzone: ${TZ}"
fi

# Konfiguration schreiben, Datenbank vorbereiten, Administrator anlegen
php /app/src/cli/setup.php

php-fpm --daemonize

# Kurz prüfen, ob php-fpm tatsächlich läuft
i=0
while [ $i -lt 10 ]; do
    if [ -S /var/run/php-fpm.sock ] || netstat -lnt 2>/dev/null | grep -q ':9000'; then
        break
    fi
    i=$((i + 1))
    sleep 1
done

echo "[run] nginx auf Port 8099"
exec nginx -g 'daemon off;'
