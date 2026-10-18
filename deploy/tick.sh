#!/usr/bin/env bash
# Run one game tick on the server. Cron calls this; deploy.sh ships it to mb/tick.sh.
#
#   bash ~/www/jacobp259.sg-host.com/mb/tick.sh
#
# update.php must run with the repo root as CWD (it includes "app/include/...",
# which here resolves through the mb/app -> ../public_html symlink).
#
# The database settings come from ~/.mb-env -- server-owned, chmod 600, never
# deployed; deploy/mb-env.example is the template.
#
# The -d flags are the parts of docker/php/php.ini the code depends on. The
# docker image sets them globally; the host's CLI PHP does not, so they go here.
# sendmail_path=/bin/true matches local: update.php mails a hardcoded address
# on every tick (update.php, "Sending email to Mako"). Set MB_TICK_MAIL=1 in
# ~/.mb-env only once that is gone.
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

ENV_FILE="$HOME/.mb-env"
[ -r "$ENV_FILE" ] || { echo "tick: $ENV_FILE missing" >&2; exit 1; }
set -a; . "$ENV_FILE"; set +a

PHP="${MB_PHP:-/usr/local/php83/bin/php}"
LOG_DIR="$HOME/logs"
mkdir -p "$LOG_DIR"

mail_flag=(-d sendmail_path=/bin/true)
[ "${MB_TICK_MAIL:-0}" = 1 ] && mail_flag=()

# One tick at a time: a slow tick must not overlap the next cron firing.
exec 9>"$LOG_DIR/tick.lock"
if ! flock -n 9; then
    echo "$(date -u '+%F %T') skipped: previous tick still running" >> "$LOG_DIR/tick.log"
    exit 0
fi

status=0
{
    echo "== $(date -u '+%F %T') tick start ($(cat REVISION 2>/dev/null || echo unknown))"
    "$PHP" \
        -d short_open_tag=On \
        -d output_buffering=4096 \
        -d date.timezone=UTC \
        -d max_execution_time=300 \
        -d memory_limit=256M \
        -d display_errors=stderr \
        -d error_reporting=E_ALL \
        "${mail_flag[@]}" \
        update.php || status=$?
    echo "== $(date -u '+%F %T') tick end (exit $status)"
} >> "$LOG_DIR/tick.log" 2>&1
exit "$status"
