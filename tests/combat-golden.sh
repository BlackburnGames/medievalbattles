#!/usr/bin/env bash
# Golden-master test for combat: attack.php, attackr.php, attackm.php and
# scattack.php, driven over HTTP from the fixture world. See the header of
# tests/combat-golden.php for what each scenario is and why.
#
#   ./tests/combat-golden.sh          compare this run against the golden file
#   ./tests/combat-golden.sh --accept overwrite the golden file with this run
#
# The same review rule as the tick: read the diff before accepting it, and be
# sure every number that moved is one you meant to move. A scenario marked
# QUIRK pins a recorded bug as it is -- fixing it is the one reason its lines
# may change, and the fix removes its $GAMEDATA['quirks'] entry.
#
# The script reloads the schema per scenario and leaves the last one's world
# behind, so it resets the database when it is done.
set -euo pipefail

cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1   # stop Git Bash rewriting container paths

GOLDEN="tests/golden/combat.txt"
ACTUAL="$(mktemp)"
trap 'rm -f "$ACTUAL"' EXIT

ACCEPT=0
[ "${1:-}" = "--accept" ] && ACCEPT=1

echo "Running combat scenarios..."
if ! docker compose exec -T web php /repo/tests/combat-golden.php > "$ACTUAL"; then
  echo "FAIL: the combat scenarios did not run."
  cat "$ACTUAL"
  exit 1
fi
bash tests/reset-db.sh > /dev/null

if [ "$ACCEPT" = "1" ] || [ ! -f "$GOLDEN" ]; then
  mkdir -p "$(dirname "$GOLDEN")"
  cp "$ACTUAL" "$GOLDEN"
  echo "Wrote golden: $GOLDEN ($(grep -c '^### ' "$GOLDEN") scenarios)"
  exit 0
fi

if diff -u "$GOLDEN" "$ACTUAL" > /tmp/combat-diff.txt; then
  echo "PASS: combat matches $GOLDEN ($(grep -c '^### ' "$GOLDEN") scenarios)"
  exit 0
fi

echo "FAIL: combat differs from $GOLDEN"
echo
head -80 /tmp/combat-diff.txt
echo
echo "(full diff in /tmp/combat-diff.txt; re-accept with ./tests/combat-golden.sh --accept)"
exit 1
