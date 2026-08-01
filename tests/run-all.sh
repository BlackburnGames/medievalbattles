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

echo
if [ "$status" = "0" ]; then
  echo "ALL TESTS PASSED"
else
  echo "SOME TESTS FAILED"
fi
exit "$status"
