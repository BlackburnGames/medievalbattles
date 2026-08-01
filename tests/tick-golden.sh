#!/usr/bin/env bash
# Golden-master test for the game tick.
#
# update.php is the best test target in the codebase: pure database-in,
# database-out, with no HTML, session or auth involved. It is also the densest
# game logic and the highest regression risk, so it gets pinned first.
#
#   ./tests/tick-golden.sh          compare this run against the golden file
#   ./tests/tick-golden.sh --accept overwrite the golden file with this run
#
# Accepting a new golden is a reviewable act: diff it first and be sure every
# changed number is one you meant to change.
set -euo pipefail

cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1   # stop Git Bash rewriting container paths

GOLDEN="tests/golden/tick.txt"
ACTUAL="$(mktemp)"
trap 'rm -f "$ACTUAL"' EXIT

ACCEPT=0
[ "${1:-}" = "--accept" ] && ACCEPT=1

bash tests/reset-db.sh > /dev/null

echo "Running tick (update.php)..."
# Captured rather than discarded. Most of it is a wall of deprecation notices
# and the database state is what this test really asserts on, but the tick is
# outside the smoke crawl, so a failing query here would be reported by nothing
# at all -- which is exactly how the dead research and lost-army bugs survived.
TICK_LOG="$(mktemp)"
trap 'rm -f "$ACTUAL" "$TICK_LOG"' EXIT
docker compose exec -T -w /repo web php update.php > "$TICK_LOG" 2>&1

# Matched against stripped text: with html_errors on PHP emits "<b>Warning</b>:"
# and a pattern expecting "Warning:" silently matches nothing.
if grep -q 'mysqli_' "$TICK_LOG"; then
  echo "FAIL: the tick reported query errors."
  echo
  grep -oE 'mysqli_[a-z_]+\(\): .* on line [0-9]+' "$TICK_LOG" | sort -u | sed 's/^/  /'
  echo
  echo "Regenerate the full inventory with: bash tests/query-audit.sh"
  exit 1
fi

docker compose exec -T web php /repo/tests/dump-state.php > "$ACTUAL"

if [ "$ACCEPT" = "1" ] || [ ! -f "$GOLDEN" ]; then
  mkdir -p "$(dirname "$GOLDEN")"
  cp "$ACTUAL" "$GOLDEN"
  echo "Wrote golden: $GOLDEN ($(wc -l < "$GOLDEN") lines)"
  exit 0
fi

if diff -u "$GOLDEN" "$ACTUAL" > /tmp/tick-diff.txt; then
  echo "PASS: tick output matches $GOLDEN"
  exit 0
fi

echo "FAIL: tick output differs from $GOLDEN"
echo
head -60 /tmp/tick-diff.txt
echo
echo "(full diff in /tmp/tick-diff.txt; re-accept with ./tests/tick-golden.sh --accept)"
exit 1
