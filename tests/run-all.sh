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

# Rebuilds the fixture per scenario and resets the database when it is done,
# so it neither needs nor leaves anything for the tests around it.
echo
echo "=== combat golden master ==="
if ! bash tests/combat-golden.sh; then
  status=1
fi

# The orders an empire gives itself, on the same harness and the same terms.
echo
echo "=== actions golden master ==="
if ! bash tests/actions-golden.sh; then
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

# What a new empire starts with, by race and class. The crawl cannot sign up,
# because signup rolls dice. Reloads the schema before and after.
echo
echo "=== signup grants ==="
if ! docker compose exec -T web php /repo/tests/signup.php; then
  status=1
fi

# Both world wipes put the administrators' kingdoms back to what signup makes,
# checked against a twin signed up after each. Reloads the schema likewise.
echo
echo "=== new game resets administrators ==="
if ! docker compose exec -T web php /repo/tests/newgame.php; then
  status=1
fi

# An ended round beside the running one, left byte-identical by the tick and
# every administrator action (docs/rounds.md). Reloads the schema likewise.
echo
echo "=== round isolation ==="
if ! docker compose exec -T web php /repo/tests/round-isolation.php; then
  status=1
fi

# A round ended into its results, and the next opened, joined, started and
# ticked alone, through bin/round.php. Reloads the schema likewise.
echo
echo "=== round lifecycle ==="
if ! docker compose exec -T web php /repo/tests/rounds.php; then
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

# Sessions over HTTP: cookie flags, fixation, logout, cross-site requests,
# revocation on a password change, and expiry. Uses poor@ and restores its
# password, so it needs nothing the barter test consumed.
echo
echo "=== sessions ==="
if ! docker compose exec -T web php /repo/tests/session.php; then
  status=1
fi

# The schema files and the migrations build the same databases. Scratch
# databases of its own, so order-independent.
echo
echo "=== migrations ==="
if ! bash tests/migrations.sh; then
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

# The second order: a value read back out of the database and interpolated
# into a later query. Its own baseline, as with the stored output audit.
echo
echo "=== SQL injection audit (stored) ==="
if ! docker compose exec -T web php /repo/tests/sql-injection-audit.php --stored; then
  status=1
fi

# Queries that still build their SQL out of values rather than binding them
# with mb_query(). Per-file counts that may only fall.
echo
echo "=== unbound queries audit ==="
if ! docker compose exec -T web php /repo/tests/unbound-queries-audit.php; then
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

# Every query on a round's tables confined to one round (docs/rounds.md).
echo
echo "=== round audit ==="
if ! docker compose exec -T web php /repo/tests/round-audit.php; then
  status=1
fi

echo
if [ "$status" = "0" ]; then
  echo "ALL TESTS PASSED"
else
  echo "SOME TESTS FAILED"
fi
exit "$status"
