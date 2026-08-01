#!/usr/bin/env bash
# Differential check for the register_globals port.
#
# tests/register-globals-audit.php is static and flow-insensitive: a variable
# assigned in one branch and read in another looks assigned, so the audit can
# pass while a page is still relying on injection. That is exactly how gc.php
# slipped through -- the mgl branch read $gid, which a *later* branch assigns.
#
# This catches that class by rendering a fixed set of parameterised URLs twice,
# once with MB_REGISTER_GLOBALS=1 and once with 0, and diffing the bodies. Any
# difference is a page still depending on the shim.
#
#   bash tests/register-globals-diff.sh
#
# The database is reset before each pass, so handlers that write are fine as
# long as they are deterministic. Restores the compose file's original setting
# and leaves the container running on it.

set -uo pipefail
cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1

original=$(grep -oE 'MB_REGISTER_GLOBALS: "[01]"' docker-compose.yml)

# Parameterised entry points, one per line: every handler that takes input and
# can be driven with a GET. Most of the game's forms are <form type=post>, and
# `type` is not a form attribute, so they submit as GET anyway.
urls() {
  cat <<'URLS'
main.php?pageid=news
main.php?pageid=explore
main.php?pageid=research
main.php?pageid=construct
main.php?pageid=military
main.php?pageid=intel
main.php?pageid=messaging
main.php?pageid=govt
main.php?pageid=guild
research.php?first=1&ur1=5&ur2=3
cresearch.php?first=1&ur1=2
aconstruct.php?update2=1&uhome=10&ufarm=5
wconstruct.php?shortsword=1
equip.php?update=1&uwarrior=3
military.php?recruit=1&urecruit=25
trade.php?trains=1&ugolem=2
animate.php?trains=1&ugolem=1
disband.php?trains=1&uwarrior=2
cexplore.php?cexploreland=3&cexploremt=1
intel.php?gather=1&empvalue=2
messaging.php?sendmessage=1&empvalue=2&umessage=hello
vmessages.php?delete=1&deletem=1
govt.php?empvalue=2
gc.php?pageid=mgl&gid=1
gmembers.php?leave=false
preferences.php?empnews=true
scoreboard.php?pageid=2
sl.php?update=1&thesetname=Test&theseturl=x&thesetnotice=y
barter.php?method=sell&type=gold&amount=100
sforum.php?topicid=1
gforums.php?topicid=1
URLS
}

capture() {              # capture <setting> <outfile>
  sed -i "s/MB_REGISTER_GLOBALS: \"[01]\"/MB_REGISTER_GLOBALS: \"$1\"/" docker-compose.yml
  docker compose up -d web > /dev/null 2>&1
  bash tests/reset-db.sh > /dev/null 2>&1
  local jar; jar=$(mktemp)
  : > "$2"
  curl -s -c "$jar" -b "$jar" -o /dev/null \
    -d 'email=tester@example.com&pw=test1234&login=1' \
    http://127.0.0.1:8080/checklogin.php
  local u
  while read -r u; do
    [ -z "$u" ] && continue
    echo "##### $u" >> "$2"
    # Strip clock readings; several pages print the tick time.
    curl -s -c "$jar" -b "$jar" -H 'X-MB-Show-Errors: 1' "http://127.0.0.1:8080/$u" \
      | sed 's/[0-9]\{1,\}:[0-9]\{2\}//g' >> "$2"
  done < <(urls)
  rm -f "$jar"
}

on=$(mktemp); off=$(mktemp)
echo "Capturing with register_globals ON..."
capture 1 "$on"
echo "Capturing with register_globals OFF..."
capture 0 "$off"

sed -i "s/MB_REGISTER_GLOBALS: \"[01]\"/$original/" docker-compose.yml
docker compose up -d web > /dev/null 2>&1

count=$(urls | grep -c .)
if diff -q "$on" "$off" > /dev/null; then
  echo "PASS: identical output across $count parameterised flows."
  rm -f "$on" "$off"
  exit 0
fi

echo "FAIL: output differs, so something still depends on register_globals:"
diff "$on" "$off" | head -60
echo
echo "(full captures: $on and $off)"
exit 1
