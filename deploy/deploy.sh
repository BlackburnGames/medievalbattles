#!/usr/bin/env bash
# Ship a commit to SiteGround.
#
#   bash deploy/deploy.sh                # dry run of HEAD: lists what would change
#   bash deploy/deploy.sh --go           # deploy HEAD
#   bash deploy/deploy.sh --go <ref>     # deploy any commit, tag or branch (also how to roll back)
#
# What ships is `git archive <ref>`, never the working tree, so uncommitted
# work -- another session's included -- cannot leak onto the server.
#
# Layout on the server (see docs/deployment.md):
#   $SITE/public_html   <- app/          (the docroot)
#   $SITE/mb            <- update.php, bin/, db/, db.sql, tick.sh, vendor/
#   $SITE/mb/app        -> ../public_html, so update.php and bin/ find app/ as they do locally
#
# Server-owned files are never sent and never deleted: public_html/.htaccess
# (PHP version, database settings, error display), .well-known/ (SSL
# validation), php.ini / .user.ini, and ~/.mb-env (the tick's settings).
#
# vendor/ is not in git. It is built in the local web container from the
# deployed commit's own composer.lock (--no-dev) and shipped with the archive,
# so the server needs no Composer and no network.
#
# Schema migrations (bin/migrate.php) run on the server from the staged copy,
# before any file moves: a failed migration leaves the site's files as they
# were. So a migration must leave the previous commit's code working -- add
# first, remove in a later deploy. A dry run shows what is pending.
#
# Refuses to deploy unless the site answers 401, i.e. is behind Site Tools'
# Protected URLs. CLAUDE.md: do not expose this app to a network. Pass
# --public only once that decision has been made.
#
# Needs ssh, tar, git and the local docker stack running (for Composer);
# rsync runs on the server.
set -euo pipefail

HOST_ALIAS="${MB_DEPLOY_HOST:-siteground-mb}"
SITE='www/jacobp259.sg-host.com'     # relative to the server's $HOME
URL='https://jacobp259.sg-host.com/'

go=0
public=0
ref=HEAD
for arg in "$@"; do
    case "$arg" in
        --go)     go=1 ;;
        --public) public=1 ;;
        -h|--help) sed -n '2,34p' "$0"; exit 0 ;;
        -*)       echo "unknown option: $arg" >&2; exit 2 ;;
        *)        ref="$arg" ;;
    esac
done

cd "$(git rev-parse --show-toplevel)"
export MSYS_NO_PATHCONV=1   # stop Git Bash rewriting paths in the docker call

sha=$(git rev-parse --verify --quiet "$ref^{commit}") || { echo "no such commit: $ref" >&2; exit 2; }
short=${sha:0:12}

if [ -n "$(git status --porcelain)" ]; then
    echo "note: the working tree has uncommitted changes; they are NOT deployed, only $short is."
fi

# --- Gate: the site must be password-protected --------------------------------
code=$(curl -s -o /dev/null -m 20 -w '%{http_code}' "$URL" || echo 000)
if [ "$public" -ne 1 ] && [ "$code" != 401 ]; then
    echo "refusing: $URL answered $code, not 401." >&2
    echo "Protect it in Site Tools > Security > Protected URLs first, or pass --public" >&2
    echo "once the decision to expose the game has actually been made." >&2
    exit 1
fi

# --- Gate: the server must be configured --------------------------------------
if ! ssh "$HOST_ALIAS" "grep -q MB_DB_NAME ~/$SITE/public_html/.htaccess 2>/dev/null"; then
    echo "refusing: ~/$SITE/public_html/.htaccess has no database settings." >&2
    echo "Install it from deploy/htaccess.example first (docs/deployment.md)." >&2
    exit 1
fi
# The migrations read their settings from here, as the tick does.
if ! ssh "$HOST_ALIAS" "grep -q MB_DB_NAME ~/.mb-env 2>/dev/null"; then
    echo "refusing: ~/.mb-env has no database settings." >&2
    echo "Install it from deploy/mb-env.example first (docs/deployment.md)." >&2
    exit 1
fi

# --- Gate: Composer runs in the local web container ---------------------------
if ! docker compose exec -T web true 2>/dev/null; then
    echo "refusing: the local web container is not running (docker compose up -d)." >&2
    exit 1
fi

mode=dry-run
[ "$go" -eq 1 ] && mode=deploy
echo "$mode: $short ($(git log -1 --format=%s "$sha")) -> $HOST_ALIAS:~/$SITE"

# --- Ship ---------------------------------------------------------------------
# The archive is unpacked into a staging directory outside the docroot, and the
# server's rsync moves it into place, so a dropped connection leaves the live
# site as it was rather than half-written.
stage=".mb-deploy/$sha"
git archive --format=tar "$sha" app update.php bin db db.sql deploy/tick.sh \
| ssh "$HOST_ALIAS" "rm -rf ~/$stage && mkdir -p ~/$stage && tar -x -C ~/$stage"

echo "== vendor (composer install --no-dev, from $short's composer.lock)"
git archive --format=tar "$sha" composer.json composer.lock \
| docker compose exec -T web sh -c '
    set -e; d=$(mktemp -d); trap "rm -rf $d" EXIT
    tar -x -C "$d"
    composer install -d "$d" --no-dev --no-interaction --no-progress --quiet --classmap-authoritative >&2
    tar -c -C "$d" vendor' \
| ssh "$HOST_ALIAS" "tar -x -C ~/$stage"

ssh "$HOST_ALIAS" bash -s -- "$SITE" "$sha" "$go" <<'REMOTE'
set -euo pipefail
SITE="$HOME/$1"; SHA="$2"; GO="$3"
STAGE="$HOME/.mb-deploy/$SHA"
trap 'rm -rf "$STAGE"' EXIT

mv "$STAGE/deploy/tick.sh" "$STAGE/tick.sh"; rmdir "$STAGE/deploy"
echo "$SHA" > "$STAGE/REVISION"

flags=(-rlt --delete --checksum --itemize-changes)
[ "$GO" = 1 ] || flags+=(--dry-run)

# Server-owned: never sent, never deleted.
protect=(
    --exclude=/.htaccess --exclude=/.well-known/
    --exclude=/php.ini --exclude=/.user.ini
)

# Migrations first, from the staged copy, so a failure stops here with the
# live files untouched. ~/.mb-env holds the settings the tick uses too.
set -a; . "$HOME/.mb-env"; set +a
PHP="${MB_PHP:-/usr/local/php83/bin/php}"
echo "== migrations"
if [ "$GO" = 1 ]; then
    "$PHP" "$STAGE/bin/migrate.php" migrate || { echo "migrations failed: no files were deployed" >&2; exit 1; }
else
    "$PHP" "$STAGE/bin/migrate.php" status || true
fi

[ "$GO" = 1 ] && mkdir -p "$SITE/mb"
echo "== public_html"
rsync "${flags[@]}" "${protect[@]}" "$STAGE/app/" "$SITE/public_html/"
echo "== mb"
# /app is excluded both ways: the staged copy went to public_html above, and
# the server's mb/app is the symlink to it.
rsync "${flags[@]}" --exclude=/app "$STAGE/" "$SITE/mb/"

if [ "$GO" = 1 ]; then
    [ -L "$SITE/mb/app" ] || ln -s ../public_html "$SITE/mb/app"
    echo "deployed $SHA"
else
    echo "(dry run: nothing changed; pass --go to deploy)"
fi
REMOTE
