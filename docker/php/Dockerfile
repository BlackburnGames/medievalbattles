# Build for either PHP version:
#
#   docker compose build                          # 8.3, the Phase 2 target
#   docker compose build --build-arg PHP_VERSION=5.6-apache
#
# 5.6 was the Phase 1 baseline because the mysql_* extension is fatal from PHP
# 7.0 onward. Those call sites are converted, so 8.3 is now the default and 5.6
# stays buildable only as the behavioural reference the golden master came from.
#
# Nothing is installed via apt: the 5.6 images are archived and their Debian
# jessie repos are dead, so any apt call breaks that build. mysqli builds
# against bundled mysqlnd and needs no system packages on either version.
ARG PHP_VERSION=8.3-apache
FROM php:${PHP_VERSION}

RUN docker-php-ext-install mysqli

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
