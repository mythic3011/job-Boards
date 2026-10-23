# shellcheck shell=bash
# Sourced by shard-project-helper/scripts/cmd/check.sh.
version="$(compose exec -T app php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null || true)"
expect "Job Boards PHP runtime is 8.5" "$version" "8.5"

status="$(compose exec -T app curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1/up 2>/dev/null || true)"
expect "Laravel /up health endpoint" "$status" "200"

# Fail if the async worker exited or is repeatedly restarting.
worker="$(compose ps --status running --services | grep -cx job-boards-worker || true)"
expect "queue worker running" "$worker" "1"
