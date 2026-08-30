#!/bin/sh
# Startskript des Containers.
#
# Ohne konfigurierten Datenbank-Host bringt der Container seine eigene
# MariaDB mit; die Daten liegen dauerhaft unter /data/mysql. Ist ein Host
# gesetzt, wird nur dorthin verbunden und keine lokale Datenbank gestartet.
set -e

DATADIR=/data/mysql
SOCKET=/run/mysqld/mysqld.sock
PWFILE=/data/dbpass
DB_LOCAL=0

log() { echo "[run] $*"; }

# MariaDB-Werkzeuge heißen je nach Version mariadb* oder mysql*
tool() {
    for candidate in "$@"; do
        if command -v "$candidate" >/dev/null 2>&1; then
            echo "$candidate"
            return 0
        fi
    done
    return 1
}

MARIADBD=$(tool mariadbd mysqld) || { echo "[run] FEHLER: MariaDB-Server nicht gefunden"; exit 1; }
MARIADB=$(tool mariadb mysql) || { echo "[run] FEHLER: MariaDB-Client nicht gefunden"; exit 1; }
MARIADB_ADMIN=$(tool mariadb-admin mysqladmin) || { echo "[run] FEHLER: mariadb-admin nicht gefunden"; exit 1; }
INSTALL_DB=$(tool mariadb-install-db mysql_install_db) || true

stop_all() {
    log "Herunterfahren ..."
    [ -n "${NGINX_PID:-}" ] && kill "$NGINX_PID" 2>/dev/null || true
    [ -n "${FPM_PID:-}" ] && kill "$FPM_PID" 2>/dev/null || true
    if [ "$DB_LOCAL" = "1" ]; then
        # Sauber schließen, damit InnoDB keine Reste hinterlässt
        "$MARIADB_ADMIN" --protocol=socket --socket="$SOCKET" -uroot shutdown 2>/dev/null || true
        i=0
        while [ -n "${DB_PID:-}" ] && kill -0 "$DB_PID" 2>/dev/null && [ $i -lt 30 ]; do
            i=$((i + 1))
            sleep 1
        done
    fi
    log "Beendet."
    exit 0
}
trap stop_all TERM INT

log "OV-Budget startet ..."

mkdir -p /data/uploads /data/sessions /run/nginx
chown -R nginx:nginx /data/uploads /data/sessions
chmod 750 /data/uploads /data/sessions

# Zeitzone übernehmen, falls Home Assistant sie mitgibt
if [ -n "${TZ:-}" ] && [ -f "/usr/share/zoneinfo/${TZ}" ]; then
    cp "/usr/share/zoneinfo/${TZ}" /etc/localtime
    echo "${TZ}" > /etc/timezone
    echo "date.timezone=${TZ}" > /etc/php-current/conf.d/zz-timezone.ini
    log "Zeitzone: ${TZ}"
fi

# ------------------------------------------------------------------
# Braucht es die mitgelieferte Datenbank?
# ------------------------------------------------------------------
TARGET=$(php /app/src/cli/setup.php --db-target)

if [ "${TARGET%%:*}" = "local" ]; then
    DB_LOCAL=1
    DBNAME=${TARGET#*:}
    log "Eigene Datenbank wird verwendet (Datenbank: ${DBNAME})"

    mkdir -p "$DATADIR" /run/mysqld
    chown -R mysql:mysql "$DATADIR" /run/mysqld

    if [ ! -d "$DATADIR/mysql" ]; then
        log "Datenbank wird zum ersten Mal eingerichtet – das dauert einen Moment ..."
        [ -n "$INSTALL_DB" ] || { echo "[run] FEHLER: kein mariadb-install-db vorhanden"; exit 1; }
        "$INSTALL_DB" --user=mysql --datadir="$DATADIR" --auth-root-authentication-method=socket \
            >/dev/null 2>&1 \
            || "$INSTALL_DB" --user=mysql --datadir="$DATADIR" >/dev/null
        log "Datenverzeichnis angelegt."
    fi

    # Passwort der Anwendung einmalig erzeugen und aufbewahren
    if [ ! -s "$PWFILE" ]; then
        # -dc statt -d: behält nur Buchstaben und Ziffern, also weder
        # Zeilenumbruch noch Zeichen, die das SQL unten stören könnten
        head -c 32 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' > "$PWFILE"
        chmod 600 "$PWFILE"
        log "Datenbankpasswort erzeugt."
    fi
    DBPASS=$(cat "$PWFILE")

    log "MariaDB startet ..."
    # Netzwerk ausdrücklich einschalten: manche Vorgaben setzen skip-networking,
    # die Anwendung verbindet sich aber über 127.0.0.1:3306.
    "$MARIADBD" --user=mysql --datadir="$DATADIR" --socket="$SOCKET" \
        --skip-networking=0 --bind-address=127.0.0.1 --port=3306 &
    DB_PID=$!

    i=0
    until "$MARIADB_ADMIN" --protocol=socket --socket="$SOCKET" -uroot ping >/dev/null 2>&1; do
        i=$((i + 1))
        if [ $i -gt 60 ]; then
            echo "[run] FEHLER: MariaDB ist nicht gestartet. Protokoll oben beachten."
            exit 1
        fi
        if ! kill -0 "$DB_PID" 2>/dev/null; then
            echo "[run] FEHLER: MariaDB hat sich beendet."
            exit 1
        fi
        sleep 1
    done
    log "MariaDB läuft."

    "$MARIADB" --protocol=socket --socket="$SOCKET" -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DBNAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ovbudget'@'localhost' IDENTIFIED BY '${DBPASS}';
CREATE USER IF NOT EXISTS 'ovbudget'@'127.0.0.1' IDENTIFIED BY '${DBPASS}';
ALTER USER 'ovbudget'@'localhost' IDENTIFIED BY '${DBPASS}';
ALTER USER 'ovbudget'@'127.0.0.1' IDENTIFIED BY '${DBPASS}';
GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO 'ovbudget'@'localhost';
GRANT ALL PRIVILEGES ON \`${DBNAME}\`.* TO 'ovbudget'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    log "Datenbank und Zugang bereit."

    OVB_DB_HOST=127.0.0.1
    OVB_DB_PORT=3306
    OVB_DB_NAME="$DBNAME"
    OVB_DB_USER=ovbudget
    OVB_DB_PASS="$DBPASS"
    export OVB_DB_HOST OVB_DB_PORT OVB_DB_NAME OVB_DB_USER OVB_DB_PASS
else
    log "Externe Datenbank laut Konfiguration."
fi

# ------------------------------------------------------------------
# Anwendung einrichten und Webserver starten
# ------------------------------------------------------------------
php /app/src/cli/setup.php

php-fpm -F &
FPM_PID=$!

log "nginx auf Port 8099"

nginx -g 'daemon off;' &
NGINX_PID=$!

wait "$NGINX_PID"
