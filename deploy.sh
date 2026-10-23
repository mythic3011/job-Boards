#!/usr/bin/env bash
# One entry point for the opt-in shard-project-helper deployment.
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SHP_REF="982f940daa37b8e4a25823f4dfbf29f460875043"
SHP_GIT_URL="${SHP_GIT_URL:-https://github.com/mythic3011/linux_setup.git}"
TEMPLATE=""
die() { printf 'error: %s\n' "$*" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || die "Missing $1"; }
usage() {
  cat <<'HELP'
Job Boards deployment (SHP + PHP 8.5.12+)
  ./deploy.sh up                  first install or rebuild/start and verify
  ./deploy.sh init                prepare local-only configuration (no containers)
  ./deploy.sh check               verify runtime and patched PHP
  ./deploy.sh config              validate merged Docker Compose configuration
  ./deploy.sh security            check + Composer audit + Trivy image scan
  ./deploy.sh logs [service]      follow logs (default: app)
  ./deploy.sh status              show containers
  ./deploy.sh down                stop containers; keep all data volumes
  ./deploy.sh help
First install binds to 127.0.0.1 with self-signed TLS. Never use
'down --volumes' or 'reset' as an upgrade/rollback procedure.
HELP
}
dc() { docker compose --project-directory "$ROOT" --env-file "$ROOT/.env" "$@"; }
prepare_template() {
  if [ -n "${SHP_TEMPLATE_DIR:-}" ]; then
    TEMPLATE="$(cd "$SHP_TEMPLATE_DIR" && pwd)" || die "SHP_TEMPLATE_DIR missing"
    [ -f "$TEMPLATE/setup.sh" ] || die "Not an SHP template: $TEMPLATE"
    return
  fi
  # Compatibility with the first draft's installation path.
  if [ -f "$ROOT/vendor/linux_setup/shard-project-helper/setup.sh" ]; then
    TEMPLATE="$ROOT/vendor/linux_setup/shard-project-helper"
    return
  fi
  local checkout="$ROOT/.deploy/linux_setup"
  TEMPLATE="$checkout/shard-project-helper"
  need git
  if [ ! -f "$TEMPLATE/setup.sh" ]; then
    mkdir -p "$ROOT/.deploy"
    if [ ! -d "$checkout/.git" ]; then
      [ ! -e "$checkout" ] || die "$checkout exists but is not a Git checkout"
      git init -q "$checkout"
      git -C "$checkout" remote add origin "$SHP_GIT_URL"
    fi
    printf 'Fetching pinned shard-project-helper...\n'
    git -C "$checkout" fetch --quiet --depth 1 origin "$SHP_REF"
    git -C "$checkout" checkout --quiet --detach FETCH_HEAD
  fi
  local actual
  actual="$(git -C "$checkout" rev-parse HEAD)"
  [ "$actual" = "$SHP_REF" ] || die "SHP checkout differs from pinned $SHP_REF; update SHP_REF in reviewed code first"
}
prepare_env() {
  if [ ! -f "$ROOT/.env" ]; then
    local existing tmp
    existing="$(docker volume ls -q --filter label=com.docker.compose.project=job-boards | head -n 1)"
    [ -z "$existing" ] || die "Existing Job Boards volumes but missing .env; restore original .env to preserve secrets"
    tmp="$(mktemp "$ROOT/.env.XXXXXX")"
    trap 'rm -f "$tmp"' EXIT
    awk '
      /^PROJECT_NAME=/ {print "PROJECT_NAME=job-boards"; next}
      /^PLUGINS=/ {print "PLUGINS=tls-selfsigned job-boards-app"; next}
      /^BIND_ADDRESS=/ {print "BIND_ADDRESS=127.0.0.1"; next}
      {print}
    ' "$TEMPLATE/.env.example" > "$tmp"
    chmod 600 "$tmp"
    mv "$tmp" "$ROOT/.env"
    trap - EXIT
    printf 'Created localhost-only SHP .env (mode 600).\n'
  else
    grep -q '^PLUGINS=' "$ROOT/.env" && grep -q '^PROJECT_NAME=' "$ROOT/.env" ||
      die "Existing .env is not an SHP config. Back it up and migrate manually; no automatic overwrite."
    if grep -q '^APP_KEY=' "$ROOT/.env" && ! grep -Eq '^JOB_BOARDS_APP_KEY=.' "$ROOT/.env"; then
      die "Legacy APP_KEY found; preserve it as JOB_BOARDS_APP_KEY before switching runtimes"
    fi
  fi
  chmod 600 "$ROOT/.env"
  # Never regenerate secrets against existing persistent data. SHP's init may
  # otherwise expand @generate into a new database password before we notice.
  local project existing secret value
  project="$(sed -n 's/^PROJECT_NAME=//p' "$ROOT/.env" | tail -n 1)"
  existing="$(docker volume ls -q --filter "label=com.docker.compose.project=${project:-job-boards}" | head -n 1)"
  if [ -n "$existing" ]; then
    for secret in JOB_BOARDS_APP_KEY POSTGRES_PASSWORD; do
      value="$(sed -n "s/^${secret}=//p" "$ROOT/.env" | tail -n 1)"
      case "$value" in
        ""|@generate*) die "Existing project volumes but $secret is missing or unset. Restore the original secret before continuing." ;;
      esac
    done
  fi
  # A missing or wildcard bind silently exposes the nginx edge. Opt in explicitly.
  local bind_address
  bind_address="$(sed -n 's/^BIND_ADDRESS=//p' "$ROOT/.env" | tail -n 1)"
  if [ "$bind_address" != "127.0.0.1" ] && [ "${JOB_BOARDS_ALLOW_PUBLIC_BIND:-0}" != 1 ]; then
    die "Refusing public or missing BIND_ADDRESS (got '${bind_address:-unset}'). Use 127.0.0.1, or set JOB_BOARDS_ALLOW_PUBLIC_BIND=1 after configuring production TLS and firewall."
  fi
  grep -E '^PLUGINS=' "$ROOT/.env" | grep -Eq '(^|[[:space:]])job-boards-app([[:space:]]|$)' ||
    die "job-boards-app not enabled in existing .env; see deploy/shp/README.md"
}
prepare_launcher() {
  if [ -e "$ROOT/setup" ]; then
    grep -q 'SHP_PROJECT_ROOT' "$ROOT/setup" ||
      die "Existing ./setup is not an SHP launcher; refusing to replace it"
  fi
  "$TEMPLATE/setup.sh" project init "$ROOT"
}
init() {
  need docker
  docker compose version >/dev/null 2>&1 || die "Docker Compose v2 required"
  need openssl
  prepare_template
  prepare_env
  prepare_launcher
  "$ROOT/setup" init
  local key
  key="$(sed -n 's/^JOB_BOARDS_APP_KEY=//p' "$ROOT/.env" | tail -n 1)"
  if [ -z "$key" ]; then
    # Existing encrypted records must never be paired with a newly generated key.
    local project_name existing
    project_name="$(sed -n 's/^PROJECT_NAME=//p' "$ROOT/.env" | tail -n 1)"
    existing="$(docker volume ls -q --filter "label=com.docker.compose.project=${project_name:-job-boards}" | head -n 1)"
    [ -z "$existing" ] || die "Existing project volumes but JOB_BOARDS_APP_KEY is missing. Restore the original APP_KEY before continuing."
    # Pre-up hook also generates a key for direct ./setup users; here it is
    # needed earlier because 'docker compose build' parses required variables.
    local new_key
    new_key="base64:$(openssl rand -base64 32 | tr -d '\n')"
    (export SHP_PROJECT_ROOT="$ROOT"; . "$TEMPLATE/scripts/lib/common.sh"; env_set JOB_BOARDS_APP_KEY "$new_key")
    printf 'Generated persistent Laravel APP_KEY (value not displayed).\n'
  fi
  dc config --quiet
}
require_ready() {
  [ -f "$ROOT/.env" ] && [ -x "$ROOT/setup" ] ||
    die "Run ./deploy.sh up first"
}
cmd="${1:-help}"
shift || true
case "$cmd" in
  init) init ;;
  up)
    init
    printf 'Building patched PHP runtime with refreshed base layers...\n'
    dc build --pull app
    "$ROOT/setup" up
    "$ROOT/setup" check
    ;;
  config) init; dc config --quiet; printf 'Compose config valid.\n' ;;
  check) require_ready; "$ROOT/setup" check ;;
  security)
    require_ready
    "$ROOT/setup" check
    need trivy
    local_failure=0
    dc exec -T app composer audit --no-dev || local_failure=1
    image="$(sed -n 's/^JOB_BOARDS_IMAGE=//p' "$ROOT/.env" | tail -n 1)"
    trivy image --scanners vuln --severity HIGH,CRITICAL --exit-code 1 "${image:-job-boards:php8.5.12}" || local_failure=1
    [ "$local_failure" -eq 0 ] || die "Security audit reported findings; review Composer and Trivy output"
    ;;
  logs) require_ready; "$ROOT/setup" logs "${@:-app}" ;;
  status) require_ready; dc ps ;;
  down) require_ready; "$ROOT/setup" down ;;
  help|-h|--help) usage ;;
  *) usage >&2; die "Unknown command: $cmd" ;;
esac
