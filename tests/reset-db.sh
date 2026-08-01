#!/usr/bin/env bash
# Reset the database to the fixture world.
#
# Drops and recreates the schema rather than truncating, because the app
# creates per-settlement tables at runtime ("setmain<id>", "setmsgs<id>") that
# are not in db.sql and would otherwise leak between runs. MyISAM has no
# transactions, so a full reload is the only reliable isolation available.
set -euo pipefail

cd "$(dirname "$0")/.."

# Prevents Git Bash on Windows from rewriting container paths in docker args.
export MSYS_NO_PATHCONV=1

echo "Recreating database mbv6..."
docker compose exec -T db mysql -uroot -proot \
  -e "DROP DATABASE IF EXISTS mbv6; CREATE DATABASE mbv6 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON mbv6.* TO 'mb'@'%';" 2>/dev/null

echo "Loading schema (db.sql)..."
docker compose exec -T db mysql -uroot -proot mbv6 < db.sql 2>/dev/null

echo "Loading fixtures (db/seed.sql)..."
docker compose exec -T db mysql -uroot -proot mbv6 < db/seed.sql 2>/dev/null

echo "Database reset."
