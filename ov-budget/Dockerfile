# syntax=docker/dockerfile:1
#
# OV-Budget – Home-Assistant-Add-on und normaler Docker-Container.
#
# Basis ist bewusst das Home-Assistant-Basisimage (Alpine): der Supervisor
# setzt es ohnehin ein. PHP kommt deshalb aus den Alpine-Paketen und nicht
# aus dem offiziellen PHP-Image – dessen Helfer wie docker-php-ext-install
# gibt es hier nicht.
ARG BUILD_FROM=ghcr.io/home-assistant/amd64-base:latest
FROM ${BUILD_FROM}

ARG BUILD_ARCH=amd64

ENV OVB_UPLOAD_DIR=/data/uploads

# nginx als Webserver, MariaDB als mitgelieferte Datenbank.
# Die PHP-Version wird beim Bauen ermittelt, weil Alpine sie im Paketnamen
# führt (php84, php83 ...) und je Alpine-Fassung wechselt.
RUN set -eux; \
    apk add --no-cache nginx tzdata mariadb mariadb-client; \
    PHPV=""; \
    for v in 85 84 83 82; do \
        set -- "php$v" "php$v-fpm" "php$v-pdo" "php$v-pdo_mysql" "php$v-mbstring" \
               "php$v-session" "php$v-ctype" "php$v-fileinfo" "php$v-curl" \
               "php$v-openssl" "php$v-opcache"; \
        if apk add --no-cache --simulate "$@" >/dev/null 2>&1; then \
            echo "Verwende PHP $v"; \
            apk add --no-cache "$@"; \
            PHPV="$v"; \
            break; \
        fi; \
    done; \
    if [ -z "$PHPV" ]; then echo "Kein vollstaendiges PHP-Paketset gefunden"; exit 1; fi; \
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
    io.hass.name="OV-Budget" \
    io.hass.description="Pseudo-Budgetverwaltung, Wunschliste und Aufgaben für einen THW-Ortsverband" \
    io.hass.type="addon" \
    io.hass.arch="${BUILD_ARCH}"
