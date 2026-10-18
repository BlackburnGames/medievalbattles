# PHP 8.3, the version the host runs.
#
# 5.6 was the Phase 1 baseline, and this file once built either version. It no
# longer does: main stopped running on 5.6 at the tag php5.6-freeze, and the
# 5.6 build lives there (CLAUDE.md). That is also why apt is allowed again --
# the 5.6 images' Debian jessie repos are dead, so the old file used none.
ARG PHP_VERSION=8.3-apache
FROM php:${PHP_VERSION}

# mysqli for the game; pdo_mysql for Phinx, which runs the schema migrations
# (docs/modernization.md). unzip because Composer unpacks its downloads with it.
RUN apt-get update  && apt-get install -y --no-install-recommends unzip  && rm -rf /var/lib/apt/lists/*  && docker-php-ext-install mysqli pdo_mysql

# Composer, pinned. vendor/ is installed into the mounted repo (/repo/vendor),
# not into the image, because the repo is mounted over anything built here:
#   docker compose exec web composer install -d /repo
COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-medievalbattles.ini
COPY compat.php /opt/mb/compat.php
COPY prepend.php /opt/mb/prepend.php

# app/.htaccess sets error_reporting to 1 (E_ERROR only), which would hide the
# warnings and deprecations the smoke tests assert on. Leaving AllowOverride
# at None keeps that file inert so php.ini stays authoritative.
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    AllowOverride None' \
    '    Require all granted' \
    '</Directory>' > /etc/apache2/conf-available/mb-overrides.conf \
 && a2enconf mb-overrides

RUN mkdir -p /tmp/sessions && chown www-data:www-data /tmp/sessions

WORKDIR /var/www/html
