#!/usr/bin/env bash
# Run the whole suite against the running stack.
#
#   docker compose up -d --build     # once
#   ./tests/run-all.sh
set -uo pipefail

cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1   # stop Git Bash rewriting container paths

status=0

echo "=== tick golden master ==="
if ! bash tests/tick-golden.sh; then
  status=1
fi

# Deliberately after the tick test, which resets the database: the crawl reads
# and writes game state, so running it first would poison the tick fixture.
echo
echo "=== smoke crawl ==="
bash tests/reset-db.sh > /dev/null
if ! docker compose exec -T web php /repo/tests/smoke.php; then
  status=1
fi

# The news pipeline, driven directly rather than through a page. The crawl now
# files news rows (see its combat phase) but it cannot choose an empire name,
# and an empire name that is an XSS payload is the case this conversion exists
# for. Order-independent: it writes and deletes its own rows on scratch ids.
echo
echo "=== news pipeline ==="
if ! docker compose exec -T web php /repo/tests/news-render.php; then
  status=1
fi

# Removing an empire with a guild, votes and a settlement attached -- the two
# ways an empire ends, neither of which the crawl can take whole. Scratch ids,
# so order-independent.
echo
echo "=== empire removal ==="
if ! docker compose exec -T web php /repo/tests/empire-remove.php; then
  status=1
fi

# Concurrent requests against one barter listing. Needs the fixture's listings,
# which the crawl consumes, so it gets a fresh database of its own.
echo
echo "=== barter concurrency ==="
bash tests/reset-db.sh > /dev/null
if ! docker compose exec -T web php /repo/tests/barter-race.php; then
  status=1
fi

# Static, so order does not matter; last because it is the port checklist
# rather than a behavioural test.
echo
echo "=== register_globals audit ==="
if ! docker compose exec -T web php /repo/tests/register-globals-audit.php; then
  status=1
fi

echo
echo "=== SQL injection audit ==="
if ! docker compose exec -T web php /repo/tests/sql-injection-audit.php; then
  status=1
fi

echo
echo "=== unescaped output audit ==="
if ! docker compose exec -T web php /repo/tests/xss-audit.php; then
  status=1
fi

# The stored half, with a row read back out of the database counting as a
# source. Separate baseline so the first-order list keeps meaning what
# docs/testing.md says it means.
echo
echo "=== unescaped output audit (stored) ==="
if ! docker compose exec -T web php /repo/tests/xss-audit.php --stored; then
  status=1
fi

echo
if [ "$status" = "0" ]; then
  echo "ALL TESTS PASSED"
else
  echo "SOME TESTS FAILED"
fi
exit "$status"
