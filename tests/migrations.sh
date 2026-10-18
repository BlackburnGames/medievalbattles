#!/usr/bin/env bash
# The migrations and the schema files describe the same databases.
#
# db.sql and db/accounts.sql build the fixture (tests/reset-db.sh); the
# migrations in db/migrations/ build a real database (bin/migrate.php). A
# schema change is made in both, and this fails until they agree: it builds
# each database both ways, in scratch databases beside the fixture, and diffs
# their dumps (tests/schema-dump.php). It also runs the migrations a second
# time, which must change nothing.
set -uo pipefail

cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1

root=(docker compose exec -T db mysql -uroot -proot)
web=(docker compose exec -T web)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

if [ ! -f vendor/bin/phinx ]; then
  "${web[@]}" composer install -d /repo --no-interaction --no-progress -q || exit 1
fi

scratch="mbmig_round_sql mbmig_round_run mbmig_acct_sql mbmig_acct_run"
reset_scratch() {
  local sql=""
  for d in $scratch; do
    sql+="DROP DATABASE IF EXISTS $d;"
    [ "${1:-}" = drop ] || sql+="CREATE DATABASE $d CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON $d.* TO 'mb'@'%';"
  done
  "${root[@]}" -e "$sql" 2>/dev/null
}
reset_scratch
trap 'reset_scratch drop; rm -rf "$tmp"' EXIT

"${root[@]}" mbmig_round_sql < db.sql 2>/dev/null || { echo "FAIL: db.sql does not load"; exit 1; }
"${root[@]}" mbmig_acct_sql < db/accounts.sql 2>/dev/null || { echo "FAIL: db/accounts.sql does not load"; exit 1; }

migrate() {
  "${web[@]}" env MB_DB_NAME=mbmig_round_run MB_ACCOUNTS_DB_NAME=mbmig_acct_run \
    php /repo/bin/migrate.php migrate -q
}
migrate || { echo "FAIL: the migrations do not run"; exit 1; }

status=0
compare() {   # <label> <from schema file> <from migrations> <stage>
  "${web[@]}" php /repo/tests/schema-dump.php "$2" > "$tmp/$1.sql.txt"
  "${web[@]}" php /repo/tests/schema-dump.php "$3" > "$tmp/$1.run.txt"
  if diff -u "$tmp/$1.sql.txt" "$tmp/$1.run.txt" > "$tmp/$1.diff"; then
    echo "ok: the $1 database is the same from its schema file and from its migrations ($4)"
  else
    echo "FAIL: the $1 database differs ($4; - schema file, + migrations):"
    head -40 "$tmp/$1.diff"
    status=1
  fi
}
compare round mbmig_round_sql mbmig_round_run "first run"
compare accounts mbmig_acct_sql mbmig_acct_run "first run"

migrate || { echo "FAIL: a second run of the migrations fails"; exit 1; }
compare round mbmig_round_sql mbmig_round_run "after a second run"
compare accounts mbmig_acct_sql mbmig_acct_run "after a second run"

if [ "$status" -eq 0 ]; then
  echo
  echo "PASS: db.sql and db/accounts.sql say what the migrations do."
fi
exit "$status"
