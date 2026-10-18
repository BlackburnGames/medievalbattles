# Compare or accept a page-action golden master. Sourced by
# tests/combat-golden.sh and tests/actions-golden.sh.
#
#   run_golden <label> <scenario script> <golden file> [--accept]
#
# The scenario script reloads the schema per scenario and leaves the last
# one's world behind, so the database is reset when it is done.
run_golden() {
  local label="$1" script="$2" golden="$3" accept=0
  [ "${4:-}" = "--accept" ] && accept=1

  local actual diff
  actual="$(mktemp)"
  diff="/tmp/$(basename "$golden" .txt)-diff.txt"

  echo "Running $label scenarios..."
  if ! docker compose exec -T web php "/repo/$script" > "$actual"; then
    echo "FAIL: the $label scenarios did not run."
    cat "$actual"
    rm -f "$actual"
    return 1
  fi
  bash tests/reset-db.sh > /dev/null

  if [ "$accept" = "1" ] || [ ! -f "$golden" ]; then
    mkdir -p "$(dirname "$golden")"
    cp "$actual" "$golden"
    rm -f "$actual"
    echo "Wrote golden: $golden ($(grep -c '^### ' "$golden") scenarios)"
    return 0
  fi

  if diff -u "$golden" "$actual" > "$diff"; then
    rm -f "$actual"
    echo "PASS: $label matches $golden ($(grep -c '^### ' "$golden") scenarios)"
    return 0
  fi
  rm -f "$actual"

  echo "FAIL: $label differs from $golden"
  echo
  head -80 "$diff"
  echo
  echo "(full diff in $diff; re-accept with --accept)"
  return 1
}
