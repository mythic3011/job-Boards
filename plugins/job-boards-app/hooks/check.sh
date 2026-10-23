# shellcheck shell=bash
# Sourced by shard-project-helper/scripts/cmd/check.sh.
version="$(compose exec -T app php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
if [[ "$version" =~ ^8\.5\.([0-9]+)$ ]] && [ "${BASH_REMATCH[1]}" -ge 12 ]; then
    ok "Job Boards PHP $version (CVE-2026-109618/109619 patch floor met)"
else
    bad "Job Boards PHP patch floor NOT met (got '${version:-unavailable}', require 8.5.12+ on 8.5)"
fi

# Check the *running* PHP config in both containers; the version alone does
# not fix CVE-2026-115560. A disabled feature must be visible before requests.
for service in app job-boards-worker; do
    upload_progress="$(compose exec -T "$service" php -r 'echo ini_get("session.upload_progress.enabled");' 2>/dev/null || true)"
    expect "$service Session Upload Progress disabled (CVE-2026-115560)" "$upload_progress" "0"
done

status="$(compose exec -T app curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1/up 2>/dev/null || true)"
expect "Laravel /up health endpoint" "$status" "200"

worker="$(compose ps --status running --services | grep -cx job-boards-worker || true)"
expect "queue worker running" "$worker" "1"
