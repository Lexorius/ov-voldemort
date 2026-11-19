# syntax=docker/dockerfile:1
#
# OV-Multitool – Home-Assistant-Add-on und normaler Docker-Container.
#
# Basis ist bewusst das Home-Assistant-Basisimage (Alpine): der Supervisor
# setzt es ohnehin ein. PHP kommt deshalb aus den Alpine-Paketen und nicht
# aus dem offiziellen PHP-Image – dessen Helfer wie docker-php-ext-install
# gibt es hier nicht.
ARG BUILD_FROM=ghcr.io/home-assistant/amd64-base:latest
FROM ${BUILD_FROM}

ARG BUILD_ARCH=amd64
ARG BUILD_VERSION=
ENV OVB_VERSION=${BUILD_VERSION}

ENV OVB_UPLOAD_DIR=/data/uploads

# nginx als Webserver, MariaDB als mitgelieferte Datenbank.
# Die PHP-Version wird beim Bauen ermittelt, weil Alpine sie im Paketnamen
# führt (php84, php83 ...) und je Alpine-Fassung wechselt.
RUN set -eux; \
    # Alpine edge statt der stabilen Fassung: Sicherheitsfassungen kommen dort zuerst an (PHP 8.5.13
    # vom 19.11.2026 lag in edge, als Alpine 3.24 noch 8.5.12 lieferte). --available zieht jedes Paket
    # des Basisimages auf den Stand von edge. Greift die Umstellung nicht, bricht der Bau ab –
    # sonst liefe das Add-on unbemerkt auf dem alten Stand weiter.
    sed -i -E 's#/alpine/v[0-9]+\.[0-9]+/#/alpine/edge/#' /etc/apk/repositories; \
    cat /etc/apk/repositories; \
    grep -q '/alpine/edge/main' /etc/apk/repositories; \
    grep -q '/alpine/edge/community' /etc/apk/repositories; \
    apk upgrade --no-cache --available; \
    apk add --no-cache nginx tzdata mariadb mariadb-client poppler-utils su-exec; \
    PHPV=""; \
    for v in 85 84 83 82; do \
        set -- "php$v" "php$v-fpm" "php$v-pdo" "php$v-pdo_mysql" "php$v-mbstring" \
               "php$v-session" "php$v-ctype" "php$v-fileinfo" "php$v-curl" \
               "php$v-openssl" "php$v-gd" "php$v-exif" "php$v-zip"; \
        # OPcache ist seit PHP 8.5 fest eingebaut – ein eigenes Paket gibt es nur für ältere Fassungen.
        # Ohne diese Unterscheidung fiel der Bau trotz vorhandenem 8.5 immer auf 8.4 zurück.
        [ "$v" -ge 85 ] || set -- "$@" "php$v-opcache"; \
        if apk add --no-cache --simulate "$@" >/dev/null 2>&1; then \
            echo "Verwende PHP $v"; \
            apk add --no-cache "$@"; \
            PHPV="$v"; \
            break; \
        fi; \
    done; \
    if [ -z "$PHPV" ]; then echo "Kein vollstaendiges PHP-Paketset gefunden"; exit 1; fi; \
    # Sicherheitsfassungen: PHP2Shell (CVE-2026-109618/-109619, 21.10.2026) und die Fassungen vom
    # 19.11.2026 (u. a. CVE-2026-129496, CVE-2026-126714, CVE-2026-129289) – alles behoben ab
    # 8.5.13 / 8.4.28 / 8.3.37 / 8.2.36. Der Bau prüft, was Alpine edge geliefert hat, und warnt laut,
    # wenn es älter ist – die Anwendung zeigt die Lage in der Verwaltung und hat Schutzmaßnahmen in der php.ini.
    case "$PHPV" in 85) SOLL=8.5.13;; 84) SOLL=8.4.28;; 83) SOLL=8.3.37;; *) SOLL=8.2.36;; esac; \
    IST="$(php$PHPV -r 'echo PHP_VERSION;')"; \
    if php$PHPV -r 'exit(version_compare($argv[1], $argv[2], "<") ? 1 : 0);' "$IST" "$SOLL"; then \
        echo "PHP $IST enthält die Sicherheitsfassungen (ab $SOLL)"; \
    else \
        echo "WARNUNG: PHP $IST ist älter als $SOLL – es fehlen Sicherheitsfassungen; das Add-on neu bauen, sobald Alpine edge $SOLL liefert"; \
    fi; \
    ln -sf "/usr/bin/php$PHPV" /usr/bin/php; \
    ln -sf "/usr/sbin/php-fpm$PHPV" /usr/sbin/php-fpm; \
    ln -sfn "/etc/php$PHPV" /etc/php-current; \
    php -v; \
    php -m; \
    rm -rf /var/cache/apk/* /var/lib/mysql

# Anwendung
WORKDIR /app
COPY public/ /app/public/
COPY src/    /app/src/
COPY views/  /app/views/
COPY sql/    /app/sql/
# Version und Änderungsverlauf für die Anzeige in der Anwendung
COPY config.yaml CHANGELOG.md /app/

# Laufzeitdateien (nginx, MariaDB, Startskript, PHP-Vorlagen)
COPY docker/rootfs/ /

RUN set -eux; \
    chmod +x /run.sh; \
    rm -f /app/public/install.php /etc/nginx/http.d/default.conf; \
    cp /opt/ovbudget/php/zz-ovbudget.ini  /etc/php-current/conf.d/zz-ovbudget.ini; \
    mkdir -p /etc/php-current/php-fpm.d; \
    cp /opt/ovbudget/php/zz-ovbudget.conf /etc/php-current/php-fpm.d/zz-ovbudget.conf; \
    mkdir -p /app/config /data/uploads /data/sessions /data/mysql /run/nginx /run/mysqld; \
    chown -R nginx:nginx /app/config /data/uploads /data/sessions; \
    chown -R mysql:mysql /data/mysql /run/mysqld; \
    php -l /app/src/cli/setup.php; \
    php -l /app/public/index.php

EXPOSE 8099

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8099/health || exit 1

CMD ["/run.sh"]

LABEL \
    io.hass.name="OV-Multitool" \
    io.hass.description="Pseudo-Budgetverwaltung, Wunschliste und Aufgaben für einen THW-Ortsverband" \
    io.hass.type="addon" \
    io.hass.arch="${BUILD_ARCH}"
