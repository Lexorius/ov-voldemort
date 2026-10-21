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
    apk upgrade --no-cache; \
    apk add --no-cache nginx tzdata mariadb mariadb-client poppler-utils; \
    PHPV=""; \
    for v in 85 84 83 82; do \
        set -- "php$v" "php$v-fpm" "php$v-pdo" "php$v-pdo_mysql" "php$v-mbstring" \
               "php$v-session" "php$v-ctype" "php$v-fileinfo" "php$v-curl" \
               "php$v-openssl" "php$v-opcache" "php$v-gd" "php$v-exif" "php$v-zip"; \
        if apk add --no-cache --simulate "$@" >/dev/null 2>&1; then \
            echo "Verwende PHP $v"; \
            apk add --no-cache "$@"; \
            PHPV="$v"; \
            break; \
        fi; \
    done; \
    if [ -z "$PHPV" ]; then echo "Kein vollstaendiges PHP-Paketset gefunden"; exit 1; fi; \
    # PHP2Shell (CVE-2026-109618/-109619): gepatcht ab 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35. Liefert das
    # stabile Alpine-Repo noch eine aeltere Fassung, aus Alpine edge nachladen; reicht auch das nicht,
    # laut warnen – die Anwendung zeigt die Lage in der Verwaltung und hat Schutzmaßnahmen in der php.ini.
    case "$PHPV" in 85) SOLL=8.5.12;; 84) SOLL=8.4.27;; 83) SOLL=8.3.36;; *) SOLL=8.2.35;; esac; \
    IST="$(php$PHPV -r 'echo PHP_VERSION;')"; \
    if php$PHPV -r 'exit(version_compare($argv[1], $argv[2], "<") ? 1 : 0);' "$IST" "$SOLL"; then \
        echo "PHP $IST ist gegen PHP2Shell gepatcht (ab $SOLL)"; \
    else \
        echo "WARNUNG: PHP $IST ist verwundbar (PHP2Shell), gepatcht ab $SOLL – versuche Alpine edge"; \
        apk add --no-cache --upgrade --repository=https://dl-cdn.alpinelinux.org/alpine/edge/community "$@" || echo "edge nicht verwendbar"; \
        IST="$(php$PHPV -r 'echo PHP_VERSION;')"; \
        if php$PHPV -r 'exit(version_compare($argv[1], $argv[2], "<") ? 1 : 0);' "$IST" "$SOLL"; then \
            echo "PHP $IST aus Alpine edge – gepatcht"; \
        else \
            echo "WARNUNG: weiterhin PHP $IST – Schutzmaßnahmen der php.ini greifen; das Add-on neu bauen, sobald Alpine $SOLL liefert"; \
        fi; \
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
