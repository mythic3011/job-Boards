# shellcheck shell=bash
# Sourced by shard-project-helper/scripts/cmd/check.sh.
version="$(compose exec -T app php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
if [[ "$version" =~ ^8\.5\.([0-9]+)$ ]] && [ "${BASH_REMATCH[1]}" -ge 12 ]; then
    ok "Job Boards PHP $version (CVE-2026-109618/109619 patch floor met)"
else
    bad "Job Boards PHP patch floor NOT met (got '${version:-unavailable}', require 8.5.12+ on 8.5)"
fi

status="$(compose exec -T app curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1/up 2>/dev/null || true)"
expect "Laravel /up health endpoint" "$status" "200"

worker="$(compose ps --status running --services | grep -cx job-boards-worker || true)"
expect "queue worker running" "$worker" "1"
