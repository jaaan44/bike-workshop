#!/usr/bin/env bash
#
# Regression test for docker/entrypoint.sh's clear_stale_bootstrap_cache().
# Covers the real staging incident (docs/DEPLOYMENT.md §0.5): a
# bootstrap/cache/config.php cached before a DB credential rotation must be
# removed before `php artisan migrate --force` ever runs, or migrate reads
# the stale credential and fails.
#
# Pure bash — no PHP/Composer/Docker/database required, runs in a throwaway
# temp directory. Usage: bash docker/entrypoint.test.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export ENTRYPOINT_SOURCE_ONLY=1
source "${SCRIPT_DIR}/entrypoint.sh"

failures=0

assert() {
    local description="$1"
    if eval "$2"; then
        echo "ok - ${description}"
    else
        echo "FAIL - ${description}"
        failures=$((failures + 1))
    fi
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

mkdir -p "$tmp/bootstrap/cache/unexpected-subdir"
printf '*\n!.gitignore\n' > "$tmp/bootstrap/cache/.gitignore"
for f in config.php routes-v7.php events.php services.php packages.php; do
    echo '<?php return [];' > "$tmp/bootstrap/cache/$f"
done
echo 'keep me' > "$tmp/bootstrap/cache/.gitignore.bak"
echo 'keep me too' > "$tmp/bootstrap/cache/unexpected-subdir/file.txt"

clear_stale_bootstrap_cache "$tmp"

for f in config.php routes-v7.php events.php services.php packages.php; do
    assert "removes stale bootstrap/cache/${f}" "[ ! -e '${tmp}/bootstrap/cache/${f}' ]"
done
assert "leaves bootstrap/cache/.gitignore in place" "[ -f '${tmp}/bootstrap/cache/.gitignore' ]"
assert "leaves unrelated dotfiles in place" "[ -f '${tmp}/bootstrap/cache/.gitignore.bak' ]"
assert "leaves unrelated subdirectories in place" "[ -f '${tmp}/bootstrap/cache/unexpected-subdir/file.txt' ]"

# Idempotency: running again against an already-clean directory (the normal
# case on most boots, since .env doesn't change on every restart) must not
# error under `set -euo pipefail`.
clear_stale_bootstrap_cache "$tmp"
assert "second run against an already-clean directory does not error" "true"

# Must also be a no-op, not an error, when bootstrap/cache/ doesn't exist at
# all yet (e.g. a first-ever boot before the directory has been created).
rm -rf "$tmp/bootstrap"
clear_stale_bootstrap_cache "$tmp"
assert "is a no-op when bootstrap/cache/ doesn't exist yet" "true"

if [ "$failures" -gt 0 ]; then
    echo "${failures} assertion(s) failed"
    exit 1
fi

echo "All entrypoint cache-clearing assertions passed."
