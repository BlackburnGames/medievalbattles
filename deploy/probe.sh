#!/usr/bin/env bash
# Ask the server the questions deploy.sh, the htaccess template and tick.sh
# depend on, without deploying anything:
#
#   bash deploy/probe.sh
#   MB_PROBE_AUTH=user:pass bash deploy/probe.sh    # once the site is password-protected
#
# It writes one throwaway directory into public_html (random name, a probe.php
# and an .htaccess) and removes it on exit, whatever happens. Nothing else on
# the server is touched.
#
# What it answers:
#   web  - PHP version and SAPI; whether .htaccess SetEnv reaches getenv() (the
#          app reads its database settings that way); whether php_flag /
#          php_value are honoured (they are not under FastCGI, and then
#          display_errors and short_open_tag must be set another way); the
#          ini values the app depends on (docker/php/php.ini).
#   cli  - the same ini values for the PHP that tick.sh runs.
#   mysql - server version and default sql_mode, if ~/.mb-env exists.
set -euo pipefail

HOST_ALIAS="${MB_DEPLOY_HOST:-siteground-mb}"
SITE='www/jacobp259.sg-host.com'
URL='https://jacobp259.sg-host.com'
DIR="mb-probe-$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"

cleanup() { ssh "$HOST_ALIAS" "rm -rf ~/$SITE/public_html/$DIR"; }
trap cleanup EXIT

INI_KEYS='short_open_tag display_errors error_reporting output_buffering sendmail_path session.save_path date.timezone max_execution_time memory_limit'

ssh "$HOST_ALIAS" bash -s -- "$SITE/public_html/$DIR" "$INI_KEYS" <<'REMOTE'
set -euo pipefail
D="$HOME/$1"; KEYS="$2"
mkdir -p "$D"
cat > "$D/.htaccess" <<'EOF'
AddHandler application/x-httpd-php83 .php
SetEnv MB_PROBE setenv-works
php_flag short_open_tag on
php_value error_reporting 1
EOF
cat > "$D/probe.php" <<EOF
<?php
header('Content-Type: text/plain');
echo 'php            ', PHP_VERSION, ' (', PHP_SAPI, ")\n";
echo 'getenv         ', var_export(getenv('MB_PROBE'), true), "\n";
echo '\$_SERVER       ', var_export(isset(\$_SERVER['MB_PROBE']) ? \$_SERVER['MB_PROBE'] : null, true), "\n";
echo 'REDIRECT_      ', var_export(isset(\$_SERVER['REDIRECT_MB_PROBE']) ? \$_SERVER['REDIRECT_MB_PROBE'] : null, true), "\n";
echo 'php_value      ', ini_get('error_reporting') === '1' ? 'honoured' : 'ignored', "\n";
foreach (explode(' ', '$KEYS') as \$k) printf("%-14s %s\n", \$k, var_export(ini_get(\$k), true));
echo 'mysqli         ', extension_loaded('mysqli') ? 'loaded' : 'MISSING', "\n";
EOF
REMOTE

echo "== web ($URL/$DIR/probe.php)"
auth=()
[ -n "${MB_PROBE_AUTH:-}" ] && auth=(-u "$MB_PROBE_AUTH")
curl -s -m 20 "${auth[@]}" -w '(HTTP %{http_code})\n' "$URL/$DIR/probe.php"

echo
echo "== cli"
ssh "$HOST_ALIAS" bash -s -- "$INI_KEYS" <<'REMOTE'
PHP=/usr/local/php83/bin/php
"$PHP" -r 'echo "php            ", PHP_VERSION, " (", PHP_SAPI, ")\n";
  foreach (explode(" ", $argv[1]) as $k) printf("%-14s %s\n", $k, var_export(ini_get($k), true));
  echo "mysqli         ", extension_loaded("mysqli") ? "loaded" : "MISSING", "\n";
  echo "pdo_mysql      ", extension_loaded("pdo_mysql") ? "loaded" : "MISSING (bin/migrate.php needs it)", "\n";' "$1"
echo "flock          $(command -v flock || echo MISSING)"

echo
echo "== mysql"
if [ -r "$HOME/.mb-env" ]; then
    set -a; . "$HOME/.mb-env"; set +a
    MYSQL_PWD="$MB_DB_PASS" mysql -h "$MB_DB_HOST" -u "$MB_DB_USER" -N -e \
        "SELECT CONCAT('version        ', @@version); SELECT CONCAT('sql_mode       ', @@GLOBAL.sql_mode); SELECT CONCAT('database       ', schema_name) FROM information_schema.schemata WHERE schema_name = '$MB_DB_NAME';"
else
    echo "(skipped: no ~/.mb-env yet)"
fi
REMOTE
