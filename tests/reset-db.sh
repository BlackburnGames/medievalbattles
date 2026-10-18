#!/usr/bin/env bash
# Reset the database to the fixture world.
#
# Drops and recreates the schema rather than truncating, because the app
# creates per-settlement tables at runtime ("setmain<id>", "setmsgs<id>") that
# are not in db.sql and would otherwise leak between runs. MyISAM has no
# transactions, so a full reload is the only reliable isolation available.
#
# Two databases: the round (mbv6) and the accounts beside it (mb_accounts),
# which outlast a round -- see docs/rounds.md. Both are dropped, so an account
# left over from an earlier run cannot log into this one's kingdoms.
set -euo pipefail

cd "$(dirname "$0")/.."

# Prevents Git Bash on Windows from rewriting container paths in docker args.
export MSYS_NO_PATHCONV=1

echo "Recreating databases mbv6 and mb_accounts..."
docker compose exec -T db mysql -uroot -proot \
  -e "DROP DATABASE IF EXISTS mbv6; CREATE DATABASE mbv6 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON mbv6.* TO 'mb'@'%';
      DROP DATABASE IF EXISTS mb_accounts; CREATE DATABASE mb_accounts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON mb_accounts.* TO 'mb'@'%';" 2>/dev/null

echo "Loading schema (db.sql, db/accounts.sql)..."
docker compose exec -T db mysql -uroot -proot mbv6 < db.sql 2>/dev/null
docker compose exec -T db mysql -uroot -proot mb_accounts < db/accounts.sql 2>/dev/null

echo "Loading fixtures (db/seed.sql, db/accounts-seed.sql)..."
docker compose exec -T db mysql -uroot -proot mbv6 < db/seed.sql 2>/dev/null
docker compose exec -T db mysql -uroot -proot mb_accounts < db/accounts-seed.sql 2>/dev/null

echo "Database reset."
