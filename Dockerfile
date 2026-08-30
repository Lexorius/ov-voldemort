# syntax=docker/dockerfile:1
#
# OV-Budget – läuft als Home-Assistant-Add-on und als normaler Docker-Container.
# Home Assistant setzt BUILD_FROM aus build.yaml; ohne Angabe greift die Vorgabe.
ARG BUILD_FROM=php:8.3-fpm-alpine
FROM ${BUILD_FROM}

# Von Home Assistant gesetzt, hier nur dokumentiert
ARG BUILD_ARCH=amd64

ENV OVB_UPLOAD_DIR=/data/uploads \
    PHP_INI_DIR=/usr/local/etc/php

# nginx als Webserver, MariaDB als mitgelieferte Datenbank – damit ist das
# Add-on ohne weitere Installationen lauffähig.
RUN set -eux; \
    apk add --no-cache nginx tzdata mariadb mariadb-client; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql opcache; \
    rm -rf /var/cache/apk/* /var/lib/mysql

# Anwendung
WORKDIR /app
COPY public/ /app/public/
COPY src/    /app/src/
COPY views/  /app/views/
COPY sql/    /app/sql/

# Laufzeitdateien
COPY docker/rootfs/ /
RUN set -eux; \
    chmod +x /run.sh; \
    rm -f /app/public/install.php; \
    mkdir -p /app/config /data/uploads /data/sessions /data/mysql /run/nginx /run/mysqld; \
    chown -R www-data:www-data /app/config /data/uploads /data/sessions; \
    chown -R mysql:mysql /data/mysql /run/mysqld; \
    rm -f /etc/nginx/http.d/default.conf

EXPOSE 8099

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8099/health || exit 1

CMD ["/run.sh"]

LABEL \
    io.hass.name="OV-Budget" \
    io.hass.description="Pseudo-Budgetverwaltung, Wunschliste und Aufgaben für einen THW-Ortsverband" \
    io.hass.type="addon" \
    io.hass.arch="${BUILD_ARCH}"
