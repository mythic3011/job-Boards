#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./install.sh [bootstrap|up|deploy|reset|reset-demo|seed-admin|mark-installed|test-prepare|verify] [dev|production]
#   ./install.sh ssl-switch <self-signed|cloudflare-origin|letsencrypt|custom> [dev|production]
#
# Backward-compatible aliases:
#   full       -> deploy
#   quick      -> reset
#   setupAdmin -> seed-admin
#   skip       -> mark-installed
#   test       -> test-prepare
#
# Safe defaults:
# - non-destructive modes never call migrate:fresh
# - destructive modes require explicit confirmation
# - secrets are never printed to stdout by this script
# - credentials are only saved when INSTALL_SAVE_CREDS=true

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_BT_STATE_DIR="${INSTALL_BT_STATE_DIR:-${ROOT_DIR}/.blue-team-vm}"
INSTALL_COMPOSE_FILE="${INSTALL_COMPOSE_FILE:-${ROOT_DIR}/compose.yaml}"
INSTALL_ASSUME_YES="${INSTALL_ASSUME_YES:-}"
INSTALL_SAVE_CREDS="${INSTALL_SAVE_CREDS:-false}"
INSTALL_OUTPUT_DIR="${INSTALL_OUTPUT_DIR:-${INSTALL_BT_STATE_DIR}/runtime/install-artifacts}"
CONTAINER="jobs-boards-laravel.test"
NGINX_CONTAINER="jobs-boards-nginx"

# shellcheck source=ops/lib/common.sh
source "${ROOT_DIR}/ops/lib/common.sh"

cd "${ROOT_DIR}"

RAW_MODE="${1-}"
RAW_ARG_2="${2-}"
RAW_ARG_3="${3-}"
SETUP_MODE="${RAW_MODE:-full}"
INSTALL_SSL_SWITCH_TARGET="${INSTALL_SSL_SWITCH_TARGET:-}"
ENV_MODE="${INSTALL_ENV_MODE:-dev}"

contract_file() {
    printf '%s\n' "${ROOT_DIR}/ops/bootstrap/contract.json"
}

contract_validator() {
    printf '%s\n' "${ROOT_DIR}/ops/bootstrap/validate-contract.py"
}

ensure_contract_reader() {
    if command -v python3 >/dev/null 2>&1; then
        return 0
    fi

    if command -v jq >/dev/null 2>&1; then
        return 0
    fi

    err "Missing contract reader dependency. Install python3 or jq."
    exit 1
}

validate_install_contract() {
    ensure_contract_reader
    if command -v python3 >/dev/null 2>&1; then
        python3 "$(contract_validator)" "$(contract_file)"
        return 0
    fi

    jq empty "$(contract_file)" >/dev/null
}

contract_json_get() {
    local path_expression="$1"
    ensure_contract_reader
    if command -v python3 >/dev/null 2>&1; then
        python3 - "$(contract_file)" "${path_expression}" <<'PY'
import json
import sys

payload = json.load(open(sys.argv[1], encoding="utf-8"))
value = payload
for part in sys.argv[2].split('.'):
    value = value[part]
if isinstance(value, bool):
    print("true" if value else "false")
elif isinstance(value, (dict, list)):
    print(json.dumps(value))
else:
    print(value)
PY
        return 0
    fi

    jq -r ".${path_expression}" "$(contract_file)"
}

err() {
    printf '%s\n' "$*" >&2
}

warn() {
    err "WARNING: $*"
}

usage() {
    cat <<'EOF'
Usage:
  ./install.sh lab
  ./install.sh demo
  ./install.sh production
  ./install.sh reset-demo
  ./install.sh
  ./install.sh [bootstrap|up|deploy|reset|reset-demo|seed-admin|mark-installed|test-prepare|verify] [dev|production]
  ./install.sh ssl-switch <self-signed|cloudflare-origin|letsencrypt|custom> [dev|production]

PR3:
  lab/demo/production run bootstrap prepare/validate and runtime bridge apply.
  reset-demo remains an explicit separate command.
  No-arg interactive mode requires a TTY.

  bootstrap      Prepare .env defaults, obs runtime artifacts, and local port checks
  up             Build and start the combined local stack
  deploy         Non-destructive local deploy path (migrate --force, build assets)
  reset          Destructive local reset (migrate:fresh + roles only)
  reset-demo     Destructive local reset followed by headless demo install
  seed-admin     Create a post-install admin account without printing secrets
  ssl-switch     Hot-switch nginx SSL mode and gracefully reload nginx
  mark-installed Mark setup complete only
  test-prepare   Sync .env.testing and create the testing database
  verify         Verify the combined local stack health

Aliases:
  full -> deploy
  quick -> reset
  setupAdmin -> seed-admin
  skip -> mark-installed
  test -> test-prepare
EOF
}

prompt_pr2a_mode() {
    local choice=""

    while true; do
        printf '%s\n' "Select deployment mode:"
        printf '%s\n' "  1) lab"
        printf '%s\n' "  2) demo"
        printf '%s\n' "  3) production"
        read -r -p "> " choice
        case "${choice}" in
            1|lab) printf '%s\n' "lab"; return 0 ;;
            2|demo) printf '%s\n' "demo"; return 0 ;;
            3|production) printf '%s\n' "production"; return 0 ;;
            *) err "Invalid choice."; ;;
        esac
    done
}

run_pr2a_prepare_only() {
    local mode="$1"

    validate_install_contract
    ./bootstrap-env.sh prepare "${mode}"
    printf '%s\n' "$(contract_json_get 'migrationMessageKeys.prepareOnly')"
    if [[ "${mode}" == "reset-demo" ]]; then
        printf '%s\n' "$(contract_json_get 'migrationMessageKeys.resetDemoValidateOnlyStop')"
    fi
}

run_pr3_runtime_bridge_apply() {
    local mode="$1"

    validate_install_contract
    ./bootstrap-env.sh prepare "${mode}"

    BT_STATE_DIR="${INSTALL_BT_STATE_DIR}" \
    BT_RUNTIME_DIR="${INSTALL_BT_STATE_DIR}/runtime" \
    BT_ROOT_ENV_FILE="${ROOT_DIR}/.env" \
    BT_COMPOSE_APP_FILE="${ROOT_DIR}/compose.app.yml" \
    BT_COMPOSE_OBS_FILE="${ROOT_DIR}/compose.obs.yml" \
    "${ROOT_DIR}/ops/bootstrap/bootstrap-app.sh" apply

    BT_STATE_DIR="${INSTALL_BT_STATE_DIR}" \
    BT_RUNTIME_DIR="${INSTALL_BT_STATE_DIR}/runtime" \
    BT_ROOT_ENV_FILE="${ROOT_DIR}/.env" \
    BT_COMPOSE_APP_FILE="${ROOT_DIR}/compose.app.yml" \
    BT_COMPOSE_OBS_FILE="${ROOT_DIR}/compose.obs.yml" \
    "${ROOT_DIR}/ops/bootstrap/bootstrap-obs.sh" apply

    printf '%s\n' "PR3 runtime bridge apply completed for mode: ${mode}"
}

handle_pr2a_invocation() {
    local mode="$1"
    local extra_arg="${2:-}"

    if [[ -n "${extra_arg}" ]]; then
        err "$(contract_json_get 'migrationMessageKeys.legacy_demo_production_blocked')"
        usage
        exit 1
    fi

    run_pr2a_prepare_only "${mode}"
    exit 0
}

handle_pr3_runtime_invocation() {
    local mode="$1"
    local extra_arg="${2:-}"

    if [[ -n "${extra_arg}" ]]; then
        if [[ "${mode}" == "demo" && "${extra_arg}" == "production" ]]; then
            err "$(contract_json_get 'migrationMessageKeys.legacy_demo_production_blocked')"
        else
            err "Unsupported extra argument for mode '${mode}': ${extra_arg}"
        fi
        usage
        exit 1
    fi

    run_pr3_runtime_bridge_apply "${mode}"
    exit 0
}

if [[ -z "${RAW_MODE}" ]]; then
    if [[ ! -t 0 ]]; then
        err "$(contract_json_get 'migrationMessageKeys.nonInteractiveNoArg')"
        usage
        exit 1
    fi

    handle_pr3_runtime_invocation "$(prompt_pr2a_mode)"
fi

if [[ "${RAW_MODE}" == "lab" || "${RAW_MODE}" == "demo" || "${RAW_MODE}" == "production" ]]; then
    handle_pr3_runtime_invocation "${RAW_MODE}" "${RAW_ARG_2:-}"
fi

if [[ "${RAW_MODE}" == "ssl-switch" ]]; then
    INSTALL_SSL_SWITCH_TARGET="${RAW_ARG_2:-${INSTALL_SSL_SWITCH_TARGET:-}}"
    ENV_MODE="${RAW_ARG_3:-${INSTALL_ENV_MODE:-dev}}"
else
    ENV_MODE="${RAW_ARG_2:-${INSTALL_ENV_MODE:-dev}}"
fi

canonical_mode() {
    case "${1:-}" in
        bootstrap|up|deploy|reset|reset-demo|seed-admin|ssl-switch|mark-installed|test-prepare|verify)
            printf '%s\n' "$1"
            ;;
        full)
            printf '%s\n' "deploy"
            ;;
        quick)
            printf '%s\n' "reset"
            ;;
        setupAdmin)
            printf '%s\n' "seed-admin"
            ;;
        skip)
            printf '%s\n' "mark-installed"
            ;;
        test)
            printf '%s\n' "test-prepare"
            ;;
        *)
            return 1
            ;;
    esac
}

CANONICAL_MODE="$(canonical_mode "${SETUP_MODE}")" || {
    usage
    exit 1
}

[[ "${ENV_MODE}" == "dev" || "${ENV_MODE}" == "production" ]] || {
    usage
    exit 1
}

export WWWUSER="${WWWUSER:-$(id -u)}"
export WWWGROUP="${WWWGROUP:-$(id -g)}"

normalized_choice() {
    printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]'
}

is_choice_yes() {
    case "$(normalized_choice "${1:-}")" in
        y|yes|1|true) return 0 ;;
        *) return 1 ;;
    esac
}

is_choice_no() {
    case "$(normalized_choice "${1:-}")" in
        n|no|0|false) return 0 ;;
        *) return 1 ;;
    esac
}

should_save_credentials() {
    is_choice_yes "${INSTALL_SAVE_CREDS:-false}"
}

compose_file_display() {
    local compose_file="${INSTALL_COMPOSE_FILE}"

    if [[ "${compose_file}" == "${ROOT_DIR}/"* ]]; then
        printf './%s\n' "${compose_file#"${ROOT_DIR}"/}"
        return 0
    fi

    printf '%s\n' "${compose_file}"
}

app() {
    docker exec "${CONTAINER}" "$@"
}

nginx_app() {
    docker exec "${NGINX_CONTAINER}" "$@"
}

compose_local() {
    BT_STATE_DIR="${INSTALL_BT_STATE_DIR}" \
    BT_RUNTIME_DIR="${INSTALL_BT_STATE_DIR}/runtime" \
    bt_compose "$INSTALL_COMPOSE_FILE" "$@"
}

wait_for() {
    local label="$1" max_attempts="$2"
    shift 2

    local attempts=0
    local elapsed=0
    printf '%s ' "${label}"
    until "$@" &>/dev/null; do
        attempts=$((attempts + 1))
        elapsed=$((attempts * 2))
        if [[ "${attempts}" -ge "${max_attempts}" ]]; then
            echo ""
            err "${label} timed out after ${elapsed}s."
            exit 1
        fi
        printf '.'
        sleep 2
    done
    echo " done (${elapsed}s)"
}

normalize_env_value() {
    local raw="${1:-}"

    raw="${raw//$'\r'/}"
    raw="${raw%%#*}"
    raw="${raw#"${raw%%[![:space:]]*}"}"
    raw="${raw%"${raw##*[![:space:]]}"}"

    if [[ "${#raw}" -ge 2 ]]; then
        case "${raw}" in
            \"*\"|\'*\')
                if [[ "${raw:0:1}" == "${raw:${#raw}-1:1}" ]]; then
                    raw="${raw:1:${#raw}-2}"
                fi
                ;;
        esac
    fi

    printf '%s' "${raw}"
}

binding_host() {
    local binding="$1"
    local host="localhost"

    if [[ "${binding}" == *:* ]]; then
        host="${binding%:*}"
    fi

    case "${host}" in
        0.0.0.0|::|'')
            host="localhost"
            ;;
    esac

    printf '%s\n' "${host}"
}

binding_port() {
    local binding="$1"

    if [[ "${binding}" == *:* ]]; then
        printf '%s\n' "${binding##*:}"
        return 0
    fi

    printf '%s\n' "${binding}"
}

install_env_value() {
    local key="$1"
    local default_value="${2:-}"
    local raw

    raw="$(bt_env_file_value "${ROOT_DIR}/.env" "${key}" 2>/dev/null || true)"
    raw="$(normalize_env_value "${raw}")"

    if [[ -n "${raw}" ]]; then
        printf '%s\n' "${raw}"
        return 0
    fi

    printf '%s\n' "${default_value}"
}

install_binding_value() {
    local key="$1"
    local default_value="$2"
    install_env_value "${key}" "${default_value}"
}

install_port_value() {
    local key="$1"
    local default_value="$2"
    local binding

    binding="$(install_binding_value "${key}" "${default_value}")"
    binding_port "${binding}"
}

validate_postgres_identifier() {
    local label="$1"
    local value="$2"

    if [[ ! "${value}" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]; then
        err "Invalid PostgreSQL identifier for ${label}: ${value}"
        return 1
    fi

    return 0
}

app_url() {
    local binding
    local host
    local port

    binding="$(install_binding_value APP_SSL_PORT 443)"
    host="$(binding_host "${binding}")"
    port="$(binding_port "${binding}")"

    if [[ "${port}" == "443" ]]; then
        printf 'https://%s\n' "${host}"
        return 0
    fi

    printf 'https://%s:%s\n' "${host}" "${port}"
}

app_up_url() {
    printf '%s/up\n' "$(app_url)"
}

headless_install_app_url() {
    local explicit="${INSTALL_APP_URL:-}"
    local configured=""

    if [[ -n "${explicit}" ]]; then
        printf '%s\n' "${explicit}"
        return 0
    fi

    configured="$(install_env_value APP_URL '')"
    configured="$(normalize_env_value "${configured}")"

    if [[ -n "${configured}" ]]; then
        printf '%s\n' "${configured}"
        return 0
    fi

    app_url
}

container_is_running() {
    docker exec "${CONTAINER}" true &>/dev/null
}

require_running_container() {
    if container_is_running; then
        return 0
    fi

    err "Container '${CONTAINER}' is not running."
    err "Start containers first, or run: $0 full ${ENV_MODE}"
    return 1
}

confirm_or_abort() {
    local prompt="$1"

    if is_choice_yes "${INSTALL_ASSUME_YES:-}"; then
        return 0
    fi

    if is_choice_no "${INSTALL_ASSUME_YES:-}"; then
        err "Aborted."
        return 1
    fi

    if [[ ! -t 0 ]]; then
        err "Refusing interactive prompt in non-interactive mode: ${prompt}"
        return 1
    fi

    local answer=""
    read -r -p "${prompt} [y/N] " answer
    if ! is_choice_yes "${answer}"; then
        err "Aborted."
        return 1
    fi

    return 0
}

check_deps() {
    local missing=()
    local cmd

    for cmd in docker python3 openssl; do
        if ! command -v "${cmd}" >/dev/null 2>&1; then
            missing+=("${cmd}")
        fi
    done

    if ! docker compose version >/dev/null 2>&1; then
        missing+=("docker compose")
    fi

    if [[ "${#missing[@]}" -gt 0 ]]; then
        err "Missing required tools:"
        local entry
        for entry in "${missing[@]}"; do
            err "  ${entry}"
        done
        exit 1
    fi
}

_install_validate_port() {
    local name="$1"
    local value="$2"

    [[ "${value}" =~ ^[0-9]+$ ]] || {
        err "Invalid port for ${name}: '${value}' is not a number"
        return 1
    }

    (( value >= 1 && value <= 65535 )) || {
        err "Invalid port for ${name}: '${value}' is out of range (1-65535)"
        return 1
    }

    return 0
}

install_port_service_name() {
    case "${1:-}" in
        APP_PORT|APP_SSL_PORT)
            printf '%s\n' "nginx"
            ;;
        VITE_PORT)
            printf '%s\n' "laravel.test"
            ;;
        FORWARD_DB_PORT)
            printf '%s\n' "postgres"
            ;;
        FORWARD_REDIS_PORT)
            printf '%s\n' "redis"
            ;;
        *)
            return 1
            ;;
    esac
}

install_service_publishes_host_port() {
    local service="$1"
    local expected_port="$2"

    bt_compose_service_publishes_host_port "${INSTALL_COMPOSE_FILE}" "${service}" "${expected_port}"
}

install_port_owned_by_current_stack() {
    local key="$1"
    local port="$2"
    local service=""

    service="$(install_port_service_name "${key}")" || return 1
    install_service_publishes_host_port "${service}" "${port}"
}

_install_conflict_resolution_mode() {
    local choice="${BT_AUTO_ASSIGN_PORTS:-}"

    if is_choice_yes "${choice}"; then
        printf '%s\n' "auto"
        return 0
    fi

    if is_choice_no "${choice}"; then
        printf '%s\n' "abort"
        return 0
    fi

    if [[ ! -t 0 ]]; then
        err "Port conflicts detected in non-interactive mode."
        err "Set BT_AUTO_ASSIGN_PORTS=true to auto-assign, or fix .env manually."
        return 1
    fi

    printf '%s\n' "prompt"
}

_install_prompt_port_conflict_resolution() {
    local key="$1"
    local old_port="$2"
    shift 2
    local reserved_ports=("$@")
    local choice=""
    local candidate=""

    while true; do
        err "Conflict for ${key}=${old_port}."
        read -r -p "Choose [i]nput a port, [a]uto-assign a free port, or [q]uit: " choice

        case "$(normalized_choice "${choice}")" in
            ""|a|auto)
                if ! bt_find_free_port candidate "${reserved_ports[@]}"; then
                    err "ERROR: No free port found in range 3001-9001 for ${key}."
                    return 1
                fi

                printf '%s\n' "${candidate}"
                return 0
                ;;
            i|input|m|manual)
                read -r -p "Enter a free port for ${key}: " candidate
                _install_validate_port "${key}" "${candidate}" || continue

                if bt_port_is_reserved "${candidate}" "${reserved_ports[@]}"; then
                    err "${candidate} is already reserved by another configured service."
                    continue
                fi

                if bt_port_in_use "${candidate}"; then
                    err "${candidate} is already in use."
                    continue
                fi

                printf '%s\n' "${candidate}"
                return 0
                ;;
            q|quit|abort|n|no)
                err "Aborted."
                return 1
                ;;
            *)
                err "Invalid choice. Enter i, a, or q."
                ;;
        esac
    done
}

check_ports() {
    local -a reserved_port_vars=(PORT:3000)
    local -a port_vars=(
        APP_PORT:80
        APP_SSL_PORT:443
        VITE_PORT:5173
        FORWARD_DB_PORT:5432
        FORWARD_REDIS_PORT:6379
    )

    local -a reserved_ports=()
    local -a blocked_entries=()
    local -a blocked_messages=()
    local -a seen_entries=()

    local entry var default_value port key old_port new_port seen_entry seen_source message

    for entry in "${reserved_port_vars[@]}"; do
        var="${entry%%:*}"
        default_value="${entry##*:}"
        port="$(install_port_value "${var}" "${default_value}")"

        _install_validate_port "${var}" "${port}" || return 1

        seen_source=""
        for seen_entry in "${seen_entries[@]}"; do
            if [[ "${seen_entry%%:*}" == "${port}" ]]; then
                seen_source="${seen_entry#*:}"
                break
            fi
        done
        if [[ -n "${seen_source}" ]]; then
            blocked_entries+=("${var}:${port}")
            blocked_messages+=("  ${var} = ${port} conflicts with ${seen_source}")
            continue
        fi

        if install_port_owned_by_current_stack "${var}" "${port}"; then
            seen_entries+=("${port}:${var}")
            reserved_ports+=("${port}")
            continue
        fi

        if bt_port_in_use "${port}"; then
            blocked_entries+=("${var}:${port}")
            blocked_messages+=("  ${var} = ${port} is already in use")
            continue
        fi

        seen_entries+=("${port}:${var}")
        reserved_ports+=("${port}")
    done

    for entry in "${port_vars[@]}"; do
        var="${entry%%:*}"
        default_value="${entry##*:}"
        port="$(install_port_value "${var}" "${default_value}")"

        _install_validate_port "${var}" "${port}" || return 1

        seen_source=""
        for seen_entry in "${seen_entries[@]}"; do
            if [[ "${seen_entry%%:*}" == "${port}" ]]; then
                seen_source="${seen_entry#*:}"
                break
            fi
        done
        if [[ -n "${seen_source}" ]]; then
            blocked_entries+=("${var}:${port}")
            blocked_messages+=("  ${var} = ${port} conflicts with ${seen_source}")
            continue
        fi

        if install_port_owned_by_current_stack "${var}" "${port}"; then
            seen_entries+=("${port}:${var}")
            reserved_ports+=("${port}")
            continue
        fi

        if bt_port_in_use "${port}"; then
            blocked_entries+=("${var}:${port}")
            blocked_messages+=("  ${var} = ${port} is already in use")
            continue
        fi

        seen_entries+=("${port}:${var}")
        reserved_ports+=("${port}")
    done

    [[ "${#blocked_entries[@]}" -eq 0 ]] && return 0

    echo
    err "Port conflict detected:"
    for message in "${blocked_messages[@]}"; do
        err "${message}"
    done
    echo

    if [[ "${ENV_MODE}" == "production" ]]; then
        err "Production mode does not auto-assign host ports. Fix .env explicitly and retry."
        return 1
    fi

    local resolution_mode=""
    resolution_mode="$(_install_conflict_resolution_mode)" || return 1

    if [[ "${resolution_mode}" == "abort" ]]; then
        err "Aborted. Free the ports or update the relevant variables in .env manually."
        return 1
    fi

    for entry in "${blocked_entries[@]}"; do
        key="${entry%%:*}"
        old_port="${entry##*:}"

        if [[ "${resolution_mode}" == "auto" ]]; then
            if ! bt_find_free_port new_port "${reserved_ports[@]}"; then
                err "ERROR: No free port found in range 3001-9001 for ${key}."
                return 1
            fi
        else
            new_port="$(_install_prompt_port_conflict_resolution "${key}" "${old_port}" "${reserved_ports[@]}")" || return 1
        fi

        echo "  ${key}: ${old_port} -> ${new_port}"
        bt_upsert_env_file_value "${ROOT_DIR}/.env" "${key}" "${new_port}"
        export "${key}=${new_port}"

        reserved_ports+=("${new_port}")
        seen_entries+=("${new_port}:${key}")
    done

    echo
}

package_network_available() {
    app curl -fsS --connect-timeout 5 --max-time 15 \
        https://repo.packagist.org/packages.json -o /dev/null
}

ensure_php_dependencies() {
    if docker exec "${CONTAINER}" test -f /var/www/html/vendor/autoload.php; then
        return 0
    fi

    if package_network_available; then
        echo "Installing composer dependencies..."
        app composer install --no-interaction --prefer-dist
        return 0
    fi

    err "ERROR: local vendor/ is missing and package network access is unavailable."
    err "Run composer install with network access, or restore vendor/ before retrying."
    exit 1
}

prepare_obs_runtime() {
    local state_dir="${INSTALL_BT_STATE_DIR}"
    local runtime_dir="${state_dir}/runtime"
    local generated_env="${runtime_dir}/obs.generated.env"

    echo "Preparing obs runtime artifacts..."
    BT_STATE_DIR="${state_dir}" \
    BT_RUNTIME_DIR="${runtime_dir}" \
    BT_COMPOSE_OBS_FILE="${ROOT_DIR}/compose.obs.yml" \
    "${ROOT_DIR}/ops/bootstrap/bootstrap-obs.sh" prepare

    bt_export_env_file "${generated_env}"
}

persist_ssl_env_overrides() {
    local env_file="${ROOT_DIR}/.env"
    local key

    for key in \
        SSL_MODE \
        SSL_CERT_DOMAIN \
        SSL_CERT_ALT_NAMES \
        SSL_SELF_SIGNED_ALT_NAMES \
        SSL_CLOUDFLARE_ORIGIN_CERT_FILE \
        SSL_CLOUDFLARE_ORIGIN_KEY_FILE \
        SSL_CLOUDFLARE_ORIGIN_CERT \
        SSL_CLOUDFLARE_ORIGIN_KEY \
        SSL_ACME_CLIENT \
        SSL_ACME_EMAIL \
        SSL_ACME_CA \
        SSL_ACME_FORCE_RENEW \
        SSL_LETSENCRYPT_CHALLENGE \
        SSL_CERTBOT_CREDENTIALS_FILE \
        SSL_CUSTOM_CERT_PATH \
        SSL_CUSTOM_CA_BUNDLE_PATH \
        SSL_CUSTOM_KEY_PATH \
        CF_Token \
        CF_Zone_ID
    do
        if [[ -n "${!key+x}" ]]; then
            bt_upsert_env_file_value "${env_file}" "${key}" "${!key}"
        fi
    done
}

prepare_nginx_ssl_runtime() {
    persist_ssl_env_overrides

    local state_dir="${INSTALL_BT_STATE_DIR}"
    local runtime_dir="${state_dir}/runtime"
    local target_mode="${SSL_MODE:-$(install_env_value SSL_MODE self-signed)}"

    BT_STATE_DIR="${state_dir}" \
    BT_RUNTIME_DIR="${runtime_dir}" \
    BT_ROOT_ENV_FILE="${ROOT_DIR}/.env" \
    BT_COMPOSE_APP_FILE="${INSTALL_COMPOSE_FILE}" \
    BT_NGINX_CONTAINER="${NGINX_CONTAINER}" \
    SSL_MODE="${target_mode}" \
    "${ROOT_DIR}/ops/bootstrap/bootstrap-nginx-ssl.sh" prepare
}

prepare_runtime_inputs() {
    ./bootstrap-env.sh "${ENV_MODE}"
    prepare_nginx_ssl_runtime
    prepare_obs_runtime
}

ssl_switch_target_mode() {
    local target="${INSTALL_SSL_SWITCH_TARGET:-}"

    case "${target}" in
        self-signed|cloudflare-origin|letsencrypt|custom)
            printf '%s\n' "${target}"
            ;;
        *)
            err "ssl-switch requires one of: self-signed, cloudflare-origin, letsencrypt, custom"
            return 1
            ;;
    esac
}

start_containers() {
    if ! container_is_running; then
        check_ports
        echo "Building laravel.test image..."
        compose_local build laravel.test
        echo "Starting containers..."
        compose_local up -d
        wait_for "Container starting" 30 docker exec "${CONTAINER}" true
    fi

    ensure_php_dependencies
    wait_for "Container ready" 60 app php artisan --version
}

build_assets() {
    app npm ci
    app npm run build
}

refresh_application_caches() {
    app php artisan optimize:clear
}

enter_maintenance() {
    app php artisan down || true
}

leave_maintenance() {
    app php artisan up || true
}

seed_roles() {
    app php artisan db:seed --class=RolePermissionSeeder --force
}

mark_setup_complete() {
    if ! app php artisan tinker --execute="exit(App\Models\Setting::isSetupCompleted() ? 0 : 1);" &>/dev/null; then
        app php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"
    fi
}

write_receipt() {
    local action="$1"
    local compose_up="$2"
    local migrations_applied="$3"
    local setup_completed="$4"
    local admin_bootstrap_completed="$5"
    local destructive_mode="$6"
    local receipt_path="${INSTALL_BT_STATE_DIR}/runtime/install.receipt.json"

    mkdir -p "$(dirname "${receipt_path}")"

    python3 - "${receipt_path}" "${action}" "${ENV_MODE}" "${compose_up}" "${migrations_applied}" "${setup_completed}" "${admin_bootstrap_completed}" "${destructive_mode}" <<'PY'
from datetime import datetime, timezone
import json
import sys

receipt_path, action, env_mode, compose_up, migrations_applied, setup_completed, admin_bootstrap_completed, destructive_mode = sys.argv[1:]

def as_bool(value: str) -> bool:
    return value.lower() in {"1", "true", "yes", "y"}

payload = {
    "action": action,
    "env_mode": env_mode,
    "compose_up": as_bool(compose_up),
    "migrations_applied": as_bool(migrations_applied),
    "setup_completed": as_bool(setup_completed),
    "admin_bootstrap_completed": as_bool(admin_bootstrap_completed),
    "destructive_mode": as_bool(destructive_mode),
    "completed_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
}

with open(receipt_path, "w", encoding="utf-8") as handle:
    json.dump(payload, handle, indent=2)
    handle.write("\n")
PY
}

print_summary() {
    local mode="$1"
    local compose_file
    compose_file="$(compose_file_display)"
    local ps_cmd
    local logs_cmd

    if [[ "${INSTALL_COMPOSE_FILE}" == "${ROOT_DIR}/compose.yaml" ]]; then
        ps_cmd="docker compose ps"
        logs_cmd="docker compose logs"
    else
        ps_cmd="docker compose -f ${compose_file} ps"
        logs_cmd="docker compose -f ${compose_file} logs"
    fi

    echo ""
    echo "======================================="
    echo " Done: ${mode}"
    echo "======================================="
    echo "  App:        $(app_url)"
    echo "  Monitoring: $(app_url)/monitoring/grafana/"
    echo "  Receipt:    ${INSTALL_BT_STATE_DIR}/runtime/install.receipt.json"
    echo ""
    echo "  ${ps_cmd}   — check service health"
    echo "  ${logs_cmd} — view logs"
    echo ""
}

admin_email_input() {
    local default_email="${INSTALL_ADMIN_EMAIL:-admin@example.com}"
    local value="${INSTALL_ADMIN_EMAIL:-}"

    if [[ -n "${value}" ]]; then
        printf '%s\n' "${value}"
        return 0
    fi

    if [[ ! -t 0 ]]; then
        printf '%s\n' "${default_email}"
        return 0
    fi

    read -r -p "Admin email [${default_email}]: " value
    printf '%s\n' "${value:-${default_email}}"
}

admin_name_input() {
    local default_name="${INSTALL_ADMIN_NICKNAME:-System Administrator}"
    local value="${INSTALL_ADMIN_NICKNAME:-}"

    if [[ -n "${value}" ]]; then
        printf '%s\n' "${value}"
        return 0
    fi

    if [[ ! -t 0 ]]; then
        printf '%s\n' "${default_name}"
        return 0
    fi

    read -r -p "Admin name [${default_name}]: " value
    printf '%s\n' "${value:-${default_name}}"
}

admin_password_input() {
    local value="${INSTALL_ADMIN_PASSWORD:-}"
    local password_file="${INSTALL_ADMIN_PASSWORD_FILE:-}"

    if [[ -n "${password_file}" ]]; then
        [[ -r "${password_file}" ]] || {
            err "Admin password file [${password_file}] is not readable."
            return 1
        }
        value="$(<"${password_file}")"
    fi

    if [[ -n "${value}" ]]; then
        printf '%s\n' "${value}"
        return 0
    fi

    if [[ ! -t 0 ]]; then
        err "Admin password is required in non-interactive mode."
        err "Set INSTALL_ADMIN_PASSWORD or INSTALL_ADMIN_PASSWORD_FILE."
        return 1
    fi

    while true; do
        local first=""
        local second=""
        read -r -s -p "Admin password (min 12 chars): " first
        echo ""
        if [[ "${#first}" -lt 12 ]]; then
            err "Password must be at least 12 characters."
            continue
        fi

        read -r -s -p "Confirm password: " second
        echo ""
        if [[ "${first}" != "${second}" ]]; then
            err "Passwords do not match."
            continue
        fi

        printf '%s\n' "${first}"
        return 0
    done
}

seed_admin() {
    require_running_container || return 1
    seed_roles

    local admin_email
    local admin_name
    local admin_password

    admin_email="$(admin_email_input)" || return 1
    admin_name="$(admin_name_input)" || return 1
    admin_password="$(admin_password_input)" || return 1

    printf '%s' "${admin_password}" | docker exec -i \
        "${CONTAINER}" \
        php artisan admin:create \
        --email="${admin_email}" \
        --name="${admin_name}" \
        --password-file=php://stdin

    app php artisan optimize:clear
    write_receipt "seed-admin" "true" "false" "false" "true" "false"
    echo "Admin bootstrap completed for ${admin_email}."
}

run_headless_install() {
    local admin_email="$1"
    local admin_name="$2"
    local admin_password="$3"
    local credential_mode="none"
    local credential_file=""
    local app_name
    local local_app_url
    local timezone

    app_name="${INSTALL_APP_NAME:-$(install_env_value APP_NAME 'Jobs Boards')}"
    local_app_url="$(headless_install_app_url)"
    timezone="${INSTALL_TIMEZONE:-Asia/Hong_Kong}"

    if should_save_credentials; then
        mkdir -p "${INSTALL_OUTPUT_DIR}"
        credential_mode="json"
        credential_file="${INSTALL_OUTPUT_DIR}/headless-install-$(date +%Y%m%d-%H%M%S).json"
    fi

    if [[ "${credential_mode}" == "json" ]]; then
        printf '%s' "${admin_password}" | docker exec -i \
            "${CONTAINER}" \
            php artisan install:headless \
            --admin-email="${admin_email}" \
            --admin-password-file=php://stdin \
            --admin-name="${admin_name}" \
            --app-name="${app_name}" \
            --app-url="${local_app_url}" \
            --timezone="${timezone}" \
            --install-demo-data \
            --credential-output=json > "${credential_file}"
        chmod 600 "${credential_file}"
        echo "Bootstrap credentials saved to ${credential_file}"
    else
        printf '%s' "${admin_password}" | docker exec -i \
            "${CONTAINER}" \
            php artisan install:headless \
            --admin-email="${admin_email}" \
            --admin-password-file=php://stdin \
            --admin-name="${admin_name}" \
            --app-name="${app_name}" \
            --app-url="${local_app_url}" \
            --timezone="${timezone}" \
            --install-demo-data \
            --credential-output=none
    fi
}

postgres_container_id() {
    bt_compose_service_container_id "${INSTALL_COMPOSE_FILE}" postgres
}

prepare_test_environment() {
    require_running_container || return 1

    echo "Setting up testing environment..."
    ./bootstrap-env.sh test

    local postgres_container
    local db_user
    local test_db_name

    postgres_container="$(postgres_container_id)"
    [[ -n "${postgres_container}" ]] || {
        err "Postgres service container could not be resolved from ${INSTALL_COMPOSE_FILE}."
        return 1
    }

    db_user="$(install_env_value DB_USERNAME postgres)"
    test_db_name="$(bt_env_file_value "${ROOT_DIR}/.env.testing" DB_DATABASE 2>/dev/null || true)"
    test_db_name="$(normalize_env_value "${test_db_name}")"
    test_db_name="${test_db_name:-testing}"

    validate_postgres_identifier "DB_USERNAME" "${db_user}" || return 1
    validate_postgres_identifier "DB_DATABASE" "${test_db_name}" || return 1

    echo "Creating testing database..."
    docker exec "${postgres_container}" psql -U "${db_user}" -d postgres -c "CREATE DATABASE ${test_db_name};" 2>/dev/null || echo "  (${test_db_name} database already exists)"

    write_receipt "test-prepare" "true" "false" "false" "false" "false"

    echo ""
    echo "Testing environment ready."
    echo "Run full PostgreSQL tests with: docker exec ${CONTAINER} composer test"
}

verify_combined_stack() {
    require_running_container || return 1

    local status=0
    local nginx_state="unknown"
    local postgres_state="unknown"
    local redis_state="unknown"

    if bt_compose_service_healthy "${INSTALL_COMPOSE_FILE}" nginx; then
        nginx_state="healthy"
    else
        nginx_state="unhealthy"
        status=1
    fi

    if bt_compose_service_healthy "${INSTALL_COMPOSE_FILE}" postgres; then
        postgres_state="healthy"
    else
        postgres_state="unhealthy"
        status=1
    fi

    if bt_compose_service_healthy "${INSTALL_COMPOSE_FILE}" redis; then
        redis_state="healthy"
    else
        redis_state="unhealthy"
        status=1
    fi

    if curl -kfsS --max-time 10 "$(app_up_url)" >/dev/null; then
        :
    else
        err "HTTPS health probe failed at $(app_up_url)"
        status=1
    fi

    echo "nginx: ${nginx_state}"
    echo "postgres: ${postgres_state}"
    echo "redis: ${redis_state}"

    write_receipt "verify" "true" "false" "false" "false" "false"
    return "${status}"
}

ssl_switch_action() {
    local target_mode=""

    target_mode="$(ssl_switch_target_mode)" || return 1
    export SSL_MODE="${target_mode}"

    BT_STATE_DIR="${INSTALL_BT_STATE_DIR}" \
    BT_RUNTIME_DIR="${INSTALL_BT_STATE_DIR}/runtime" \
    BT_ROOT_ENV_FILE="${ROOT_DIR}/.env" \
    BT_COMPOSE_APP_FILE="${INSTALL_COMPOSE_FILE}" \
    BT_NGINX_CONTAINER="${NGINX_CONTAINER}" \
    SSL_MODE="${target_mode}" \
    "${ROOT_DIR}/ops/bootstrap/bootstrap-nginx-ssl.sh" switch

    if bt_compose_service_running "${INSTALL_COMPOSE_FILE}" nginx; then
        if ! curl -kfsS --max-time 10 "$(app_up_url)" >/dev/null; then
            err "HTTPS health probe failed after ssl-switch at $(app_up_url)"
            return 1
        fi
    fi

    persist_ssl_env_overrides

    write_receipt "ssl-switch" "true" "false" "false" "false" "false"
    print_summary "${SETUP_MODE}"
}

deploy_action() {
    prepare_runtime_inputs
    start_containers
    enter_maintenance
    app php artisan migrate --force
    seed_roles
    build_assets
    refresh_application_caches
    leave_maintenance
    write_receipt "deploy" "true" "true" "false" "false" "false"
    print_summary "${SETUP_MODE}"
}

reset_action() {
    confirm_or_abort "Reset mode wipes the local database. Continue?" || return 1
    prepare_runtime_inputs
    start_containers
    enter_maintenance
    app php artisan migrate:fresh --force
    seed_roles
    build_assets
    refresh_application_caches
    leave_maintenance
    write_receipt "reset" "true" "true" "false" "false" "true"
    print_summary "${SETUP_MODE}"
}

reset_demo_action() {
    local admin_email
    local admin_name
    local admin_password

    confirm_or_abort "reset-demo wipes the local database and reseeds demo data. Continue?" || return 1
    admin_email="$(admin_email_input)" || return 1
    admin_name="$(admin_name_input)" || return 1
    admin_password="$(admin_password_input)" || return 1
    prepare_runtime_inputs
    start_containers
    enter_maintenance
    app php artisan migrate:fresh --force
    run_headless_install "${admin_email}" "${admin_name}" "${admin_password}"
    build_assets
    refresh_application_caches
    leave_maintenance
    write_receipt "reset-demo" "true" "true" "true" "true" "true"
    print_summary "${SETUP_MODE}"
}

check_deps

case "${CANONICAL_MODE}" in
    bootstrap)
        prepare_runtime_inputs
        check_ports
        write_receipt "bootstrap" "false" "false" "false" "false" "false"
        print_summary "${SETUP_MODE}"
        ;;

    up)
        prepare_runtime_inputs
        start_containers
        write_receipt "up" "true" "false" "false" "false" "false"
        print_summary "${SETUP_MODE}"
        ;;

    deploy)
        deploy_action
        ;;

    reset)
        reset_action
        ;;

    reset-demo)
        reset_demo_action
        ;;

    seed-admin)
        seed_admin
        print_summary "${SETUP_MODE}"
        ;;

    ssl-switch)
        ssl_switch_action
        ;;

    mark-installed)
        require_running_container || exit 1
        mark_setup_complete
        echo "Setup marked as complete."
        write_receipt "mark-installed" "true" "false" "true" "false" "false"
        print_summary "${SETUP_MODE}"
        ;;

    test-prepare)
        prepare_test_environment
        ;;

    verify)
        verify_combined_stack
        print_summary "${SETUP_MODE}"
        ;;
esac
