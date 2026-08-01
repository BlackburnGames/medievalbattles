# PHP 5.6 is the last version where this codebase can run at all: the ~89
# files still on the mysql_* extension are fatal from PHP 7.0 onward. This is
# the Phase 1 baseline used as the behavioural reference for the port, not a
# target to stay on.
#
# The 5.6 images are archived, and their Debian jessie apt repos are dead, so
# this image deliberately installs nothing via apt. The mysql and mysqli
# extensions build against bundled mysqlnd and need no system packages.
FROM php:5.6-apache

RUN docker-php-ext-install mysql mysqli

COPY php.ini /usr/local/etc/php/conf.d/zz-medievalbattles.ini
COPY compat.php /opt/mb/compat.php

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
