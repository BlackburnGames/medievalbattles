#!/usr/bin/env bash
# The migrations and db.sql describe the same database.
#
# db.sql builds the fixture (tests/reset-db.sh); the migrations in
# db/migrations/ build a real database (bin/migrate.php). A schema change is
# made in both, and this fails until they agree: it builds the database both
# ways, in scratch databases beside the fixture, and diffs their dumps
# (tests/schema-dump.php). It also runs the migrations a second time, which
# must change nothing.
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

scratch="mbmig_sql mbmig_run"
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

"${root[@]}" mbmig_sql < db.sql 2>/dev/null || { echo "FAIL: db.sql does not load"; exit 1; }

migrate() {
  "${web[@]}" env MB_DB_NAME=mbmig_run php /repo/bin/migrate.php migrate -q
}
migrate || { echo "FAIL: the migrations do not run"; exit 1; }

status=0
compare() {   # <stage>
  "${web[@]}" php /repo/tests/schema-dump.php mbmig_sql > "$tmp/sql.txt"
  "${web[@]}" php /repo/tests/schema-dump.php mbmig_run > "$tmp/run.txt"
  if diff -u "$tmp/sql.txt" "$tmp/run.txt" > "$tmp/diff"; then
    echo "ok: the database is the same from db.sql and from the migrations ($1)"
  else
    echo "FAIL: the database differs ($1; - db.sql, + migrations):"
    head -40 "$tmp/diff"
    status=1
  fi
}
compare "first run"

migrate || { echo "FAIL: a second run of the migrations fails"; exit 1; }
compare "after a second run"

if [ "$status" -eq 0 ]; then
  echo
  echo "PASS: db.sql says what the migrations do."
fi
exit "$status"
