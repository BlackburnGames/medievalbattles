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
set -euo pipefail

cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1   # stop Git Bash rewriting container paths
source tests/lib/golden.sh

run_golden combat tests/combat-golden.php tests/golden/combat.txt "${1:-}"
