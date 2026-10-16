#!/usr/bin/env bash
# Runs this project's PHPUnit suite (phpunit.xml) inside DDEV, against the
# checkout the script is run from: the main checkout, or a coding-pipeline
# worktree under .claude/worktrees/. A worktree sits inside the DDEV mount, so
# the web container sees it at /var/www/html/<its path>.
#
# vendor/ and web/core are gitignored, so a new worktree has neither. The first
# run in one does a composer install into it, so its tests run against its own
# code rather than the main checkout's.
#
# Extra arguments go to PHPUnit, e.g. scripts/run-tests.sh --testsuite unit
set -euo pipefail

checkout="$(git rev-parse --show-toplevel)"
primary="$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")"
case "$checkout" in
  "$primary" | "$primary"/*) ;;
  *) echo "run-tests: $checkout is outside $primary, so DDEV cannot see it" >&2; exit 2 ;;
esac
container_dir="/var/www/html${checkout#"$primary"}"

# ddev finds its project from the working directory, and a worktree carries its
# own copy of .ddev/config.yaml, so always call ddev from the main checkout.
cd "$primary"
# With DDEV's Mutagen file sync, an edit made on the host reaches the container
# a moment later. Flush it first, so the tests never run against stale files.
ddev mutagen sync
ddev exec --dir "$container_dir" \
  'test -x vendor/bin/phpunit || composer install --no-interaction --no-progress'
exec ddev exec --dir "$container_dir" vendor/bin/phpunit -c phpunit.xml "$@"
