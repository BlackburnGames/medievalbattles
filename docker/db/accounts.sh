#!/usr/bin/env bash
# First-boot setup for the accounts database, beside the round database that
# MYSQL_DATABASE creates. The image runs this once, when the data volume is
# empty; tests/reset-db.sh does the same on every reset.
#
# The web user gets every privilege on the two databases and not CREATE
# DATABASE, which is why this runs as root. See docs/rounds.md.
set -euo pipefail

mysql_root=(mysql --protocol=socket -uroot -p"$MYSQL_ROOT_PASSWORD")

"${mysql_root[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS ${MB_ACCOUNTS_DB:-mb_accounts} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON ${MB_ACCOUNTS_DB:-mb_accounts}.* TO '$MYSQL_USER'@'%';
SQL

"${mysql_root[@]}" "${MB_ACCOUNTS_DB:-mb_accounts}" < /mb-db/accounts.sql
"${mysql_root[@]}" "${MB_ACCOUNTS_DB:-mb_accounts}" < /mb-db/accounts-seed.sql
