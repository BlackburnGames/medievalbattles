#!/usr/bin/env bash
# Run one of bin/'s commands on the server, with the database settings loaded.
# deploy.sh ships it to mb/cli.sh.
#
#   cd ~/www/jacobp259.sg-host.com/mb
#   ./cli.sh admin list                     # bin/admin.php list
#   ./cli.sh admin grant someone@example.com
#   ./cli.sh admin password someone@example.com
#   ./cli.sh round list                     # bin/round.php list
#   ./cli.sh migrate status                 # bin/migrate.php status
#
# bin/ reads its settings from the environment. Run bare, it finds none and
# falls back to the local Docker stack's (user mb), which does not exist here,
# and MySQL refuses it: "Access denied for user mb". This loads ~/.mb-env
# first, as tick.sh does, and uses the 8.3 CLI -- not .../bin/php, which is
# the CGI binary.
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

ENV_FILE="$HOME/.mb-env"
[ -r "$ENV_FILE" ] || { echo "cli: $ENV_FILE missing" >&2; exit 1; }
set -a; . "$ENV_FILE"; set +a

[ $# -ge 1 ] || { sed -n '5,10p' "$0" | sed 's/^# //' >&2; exit 2; }
script="bin/$1.php"; shift
[ -f "$script" ] || { echo "cli: no such command: $script" >&2; exit 2; }

exec "${MB_PHP:-/usr/local/php83/bin/php-cli}" -d short_open_tag=On -d date.timezone=UTC "$script" "$@"
