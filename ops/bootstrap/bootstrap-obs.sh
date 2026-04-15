#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
OBS_WAIT_TIMEOUT_SECONDS="${BT_OBS_WAIT_TIMEOUT_SECONDS:-90}"
OBS_GENERATED_ENV_FILE="${BT_OBS_GENERATED_ENV_FILE:-${BT_RUNTIME_DIR}/obs.generated.env}"
OBS_GENERATED_AUDIT_FILE="${BT_OBS_GENERATED_AUDIT_FILE:-${BT_RUNTIME_DIR}/obs.generated-secrets.jsonl}"
OBS_RENDERED_DIR="${BT_OBS_RENDERED_DIR:-${BT_STATE_DIR}/rendered}"
OBS_GRAFANA_SECRET_FILE="${BT_GRAFANA_SECRET_FILE:-${BT_RUNTIME_DIR}/grafana-admin-secret}"
OBS_PROMETHEUS_WEB_CONFIG_FILE="${BT_PROMETHEUS_WEB_CONFIG_FILE:-${OBS_RENDERED_DIR}/prometheus.web-config.yml}"
obs_statuses=()

run_obs_check() {
    local check_id="$1"
    local message="$2"
    local remediation="$3"
    shift 3

    if "$@"; then
        bt_emit_check "${check_id}" "obs" "${BT_STATUS_PASS}" "${message}" "${remediation}"
        obs_statuses+=("${BT_STATUS_PASS}")
        return 0
    fi

    bt_emit_check "${check_id}" "obs" "${BT_STATUS_FAIL}" "${message}" "${remediation}"
    obs_statuses+=("${BT_STATUS_FAIL}")
    return 1
}

emit_obs_summary() {
    local summary_message="$1"
    local status
    if [[ "${#obs_statuses[@]}" -eq 0 ]]; then
        status="${BT_STATUS_SKIPPED}"
    else
        status="$(bt_aggregate_statuses "${obs_statuses[@]}")"
    fi
    bt_emit_plane_summary "obs" "${status}" "${summary_message}" "Inspect obs-plane checks."
    [[ "${status}" != "${BT_STATUS_FAIL}" ]]
}

obs_check_id_suffix() {
    printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]'
}

verify_obs_logs() {
    bt_compose "${BT_COMPOSE_OBS_FILE}" exec -T promtail sh -c 'test -r /var/log/nginx/access.log || test -r /var/log/nginx/error.log'
}

verify_obs_no_ports() {
    python3 - "${BT_COMPOSE_OBS_FILE}" <<'PY'
import json
import subprocess
import sys

compose_file = sys.argv[1]
result = subprocess.run(
    ["docker", "compose", "-f", compose_file, "config", "--format", "json"],
    capture_output=True,
    text=True,
    check=True,
)
config = json.loads(result.stdout)
for service in config.get("services", {}).values():
    if service.get("ports"):
        raise SystemExit(1)
raise SystemExit(0)
PY
}

verify_obs_required_env() {
    local required=(
        "MONITORING_ADMIN_USERNAME"
        "MONITORING_PASSWORD_HASH"
        "SESSION_SECRET"
        "GRAFANA_SECRET_FILE"
        "PROMETHEUS_PASSWORD_HASH"
        "PROMETHEUS_WEB_CONFIG_FILE"
    )
    local var_name
    local missing=()
    local invalid=()
    local value
    local normalized

    for var_name in "${required[@]}"; do
        value="$(obs_effective_env_value "${var_name}" || true)"
        if [[ -z "${value}" ]]; then
            missing+=("${var_name}")
            continue
        fi

        case "${var_name}" in
            MONITORING_PASSWORD_HASH|PROMETHEUS_PASSWORD_HASH)
                normalized="$(bt_normalize_compose_dollars "${value}")"
                if ! bt_bcrypt_hash_runtime_usable "${normalized}"; then
                    invalid+=("${var_name}")
                fi
                ;;
            GRAFANA_SECRET_FILE|PROMETHEUS_WEB_CONFIG_FILE)
                if [[ ! -r "${value}" ]]; then
                    missing+=("${var_name}")
                fi
                ;;
        esac
    done

    if [[ "${#missing[@]}" -gt 0 ]]; then
        bt_warn "Obs required runtime values missing or unreadable: ${missing[*]}"
        return 1
    fi

    if [[ "${#invalid[@]}" -gt 0 ]]; then
        bt_warn "Obs required runtime hashes invalid or unusable: ${invalid[*]}"
        return 1
    fi

    return 0
}

obs_prepare_runtime_artifacts() {
    bt_mkdir "${BT_RUNTIME_DIR}"
    bt_mkdir "${OBS_RENDERED_DIR}"
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN prepare obs generated artifacts"
        return 0
    fi
    touch "${OBS_GENERATED_ENV_FILE}" "${OBS_GENERATED_AUDIT_FILE}"
    chmod 0600 "${OBS_GENERATED_ENV_FILE}" "${OBS_GENERATED_AUDIT_FILE}"
    chmod 0755 "${OBS_RENDERED_DIR}"
}

obs_render_prometheus_web_config() {
    local password_hash
    local current_path=""
    password_hash="$(bt_normalize_compose_dollars "$(obs_effective_env_value "PROMETHEUS_PASSWORD_HASH" || true)")"
    [[ -n "${password_hash}" ]] || return 1

    bt_write_file "${OBS_PROMETHEUS_WEB_CONFIG_FILE}" "$(cat <<EOF
basic_auth_users:
  admin: "${password_hash}"
EOF
)"
    if [[ "${BT_DRY_RUN}" != "1" ]]; then
        chmod 0644 "${OBS_PROMETHEUS_WEB_CONFIG_FILE}"
    fi

    current_path="$(obs_effective_env_value "PROMETHEUS_WEB_CONFIG_FILE" || true)"
    obs_set_generated_value "PROMETHEUS_WEB_CONFIG_FILE" "${OBS_PROMETHEUS_WEB_CONFIG_FILE}"
    if [[ "${current_path}" != "${OBS_PROMETHEUS_WEB_CONFIG_FILE}" ]]; then
        obs_emit_generated_audit "PROMETHEUS_WEB_CONFIG_FILE" "PROMETHEUS_PASSWORD_HASH" "rendered_runtime_config" "true" "false"
    fi
}

obs_load_generated_env() {
    bt_export_env_file "${OBS_GENERATED_ENV_FILE}"
}

obs_emit_generated_audit() {
    local target="$1"
    local source="$2"
    local mode="$3"
    local deterministic="$4"
    local user_action_required="$5"

    local payload
    payload="$(python3 - "${target}" "${source}" "${mode}" "${deterministic}" "${user_action_required}" "$(bt_now_utc)" "${BT_BUNDLE_VERSION}" <<'PY'
import json
import sys

payload = {
    "record_type": "generated_secret",
    "generated_at": sys.argv[6],
    "generated_by": sys.argv[7],
    "target_field": sys.argv[1],
    "source_field": sys.argv[2],
    "mode": sys.argv[3],
    "deterministic": sys.argv[4] == "true",
    "user_action_required": sys.argv[5] == "true",
}
print(json.dumps(payload, separators=(",", ":")))
PY
)"
    bt_append_json_record "${OBS_GENERATED_AUDIT_FILE}" "${payload}"
}

obs_set_generated_value() {
    local key="$1"
    local value="$2"
    bt_upsert_env_file_value "${OBS_GENERATED_ENV_FILE}" "${key}" "${value}"
    export "${key}=${value}"
}

obs_effective_env_value() {
    local key="$1"
    bt_env_file_value "${OBS_GENERATED_ENV_FILE}" "${key}" || bt_env_value "${key}"
}

obs_materialize_secret_file() {
    local target_key="$1"
    local source_key="$2"
    local default_path="$3"
    local current_path
    local source_value
    local check_suffix

    current_path="$(obs_effective_env_value "${target_key}" || true)"
    if [[ -n "${current_path}" ]]; then
        [[ -r "${current_path}" ]] || return 1
        return 0
    fi

    source_value="$(obs_effective_env_value "${source_key}" || true)"
    [[ -n "${source_value}" ]] || return 1

    bt_write_file "${default_path}" "${source_value}"
    if [[ "${BT_DRY_RUN}" != "1" ]]; then
        chmod 0600 "${default_path}"
    fi

    obs_set_generated_value "${target_key}" "${default_path}"
    obs_emit_generated_audit "${target_key}" "${source_key}" "materialized_secret_file" "true" "false"
    check_suffix="$(obs_check_id_suffix "${target_key}")"
    bt_emit_check "obs.bootstrap.${check_suffix}.generated" "obs" "${BT_STATUS_PASS}" "${target_key} was materialized from ${source_key}." "Review generated env provenance if operator-managed rotation is required."
    obs_statuses+=("${BT_STATUS_PASS}")
    return 0
}

obs_autofix_session_secret() {
    local current
    current="$(obs_effective_env_value "SESSION_SECRET" || true)"
    if [[ -n "${current}" ]]; then
        return 0
    fi

    local generated
    generated="$(bt_generate_secret)"
    obs_set_generated_value "SESSION_SECRET" "${generated}"
    obs_emit_generated_audit "SESSION_SECRET" "random" "generated_secret" "false" "false"
    bt_emit_check "obs.bootstrap.session_secret.generated" "obs" "${BT_STATUS_PASS}" "SESSION_SECRET was auto-generated for obs runtime." "Persist or rotate the generated secret according to operator policy."
    obs_statuses+=("${BT_STATUS_PASS}")
}

obs_autofix_password_hash() {
    local target_key="$1"
    shift

    local current
    current="$(bt_normalize_compose_dollars "$(obs_effective_env_value "${target_key}" || true)")"
    if [[ -n "${current}" ]]; then
        bt_bcrypt_hash_runtime_usable "${current}"
        return $?
    fi

    local source_key
    local source_value=""
    local check_suffix
    for source_key in "$@"; do
        source_value="$(obs_effective_env_value "${source_key}" || true)"
        [[ -n "${source_value}" ]] && break
    done

    if [[ -z "${source_value}" ]]; then
        return 1
    fi

    local generated
    generated="$(bt_bcrypt_hash "admin" "${source_value}")" || return 1
    bt_bcrypt_hash_runtime_usable "${generated}" || return 1
    obs_set_generated_value "${target_key}" "${generated}"
    obs_emit_generated_audit "${target_key}" "${source_key}" "derived_bcrypt" "true" "false"
    check_suffix="$(obs_check_id_suffix "${target_key}")"
    bt_emit_check "obs.bootstrap.${check_suffix}.generated" "obs" "${BT_STATUS_PASS}" "${target_key} was auto-derived from ${source_key}." "Review generated env provenance if operator-managed rotation is required."
    obs_statuses+=("${BT_STATUS_PASS}")
    return 0
}

ensure_obs_runtime_secrets() {
    obs_prepare_runtime_artifacts
    obs_load_generated_env

    local failed=0
    obs_autofix_session_secret || failed=1
    obs_autofix_password_hash "MONITORING_PASSWORD_HASH" "MONITORING_PASSWORD" || failed=1
    obs_autofix_password_hash "PROMETHEUS_PASSWORD_HASH" "PROMETHEUS_PASSWORD" || failed=1
    obs_materialize_secret_file "GRAFANA_SECRET_FILE" "GRAFANA_PASSWORD" "${OBS_GRAFANA_SECRET_FILE}" || failed=1
    obs_load_generated_env
    obs_render_prometheus_web_config || failed=1

    return "${failed}"
}

prepare_action() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_emit_check "obs.bootstrap.prepare.dry_run" "obs" "${BT_STATUS_SKIPPED}" "Dry-run does not materialize obs runtime artifacts." "Run without --dry-run to prepare obs runtime artifacts."
        obs_statuses+=("${BT_STATUS_SKIPPED}")
        emit_obs_summary "Obs runtime artifact prepare dry-run completed."
        return 0
    fi

    if ! ensure_obs_runtime_secrets; then
        bt_warn "Obs runtime secret preparation did not complete cleanly."
    fi

    run_obs_check "obs.bootstrap.required_env" "Obs runtime artifacts and required credentials are present after prepare." "Provide explicit values for secrets that cannot be safely derived." verify_obs_required_env || {
        emit_obs_summary "Obs runtime artifact preparation failed."
        exit 1
    }

    emit_obs_summary "Obs runtime artifacts prepared."
}

apply_action() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_emit_check "obs.bootstrap.dry_run" "obs" "${BT_STATUS_SKIPPED}" "Dry-run does not mutate obs-plane services." "Run without --dry-run to apply obs-plane changes."
        obs_statuses+=("${BT_STATUS_SKIPPED}")
        emit_obs_summary "Obs bootstrap dry-run completed."
        return 0
    fi

    if ! ensure_obs_runtime_secrets; then
        bt_warn "Obs runtime secret preparation did not complete cleanly."
    fi
    run_obs_check "obs.bootstrap.required_env" "Obs bring-up secrets and required credentials are present after audit/autofix." "Provide explicit values for secrets that cannot be safely derived." verify_obs_required_env || {
        emit_obs_summary "Obs bootstrap failed preflight."
        exit 1
    }

    bt_compose "${BT_COMPOSE_OBS_FILE}" up -d
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" grafana running "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" loki running "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" promtail running "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" prometheus running "${OBS_WAIT_TIMEOUT_SECONDS}" || true

    verify_action
}

verify_action() {
    obs_load_generated_env
    run_obs_check "obs.runtime.required_env" "Obs runtime secrets and generated auth artifacts remain present and usable." "Restore generated runtime artifacts or explicit obs runtime values before trusting obs verification." verify_obs_required_env || true
    run_obs_check "obs.grafana.healthy" "Grafana is healthy." "Inspect compose obs grafana service health." bt_compose_service_healthy "${BT_COMPOSE_OBS_FILE}" grafana || true
    run_obs_check "obs.loki.running" "Loki is running." "Inspect compose obs loki service state." bt_compose_service_running "${BT_COMPOSE_OBS_FILE}" loki || true
    run_obs_check "obs.promtail.running" "Promtail is running." "Inspect compose obs promtail service state." bt_compose_service_running "${BT_COMPOSE_OBS_FILE}" promtail || true
    run_obs_check "obs.prometheus.healthy" "Prometheus is healthy." "Inspect compose obs prometheus service health." bt_compose_service_healthy "${BT_COMPOSE_OBS_FILE}" prometheus || true
    run_obs_check "obs.auth_service.healthy" "Auth-service is healthy." "Inspect compose obs auth-service health." bt_compose_service_healthy "${BT_COMPOSE_OBS_FILE}" auth-service || true
    run_obs_check "obs.logs.read_only_mount" "Logs are readable through declared read-only shared surfaces." "Inspect promtail read-only mounts and shared artifact permissions." verify_obs_logs || true
    run_obs_check "obs.ports.none_published" "Obs-plane services do not publish host ports." "Remove host port publishing from obs compose." verify_obs_no_ports || true

    emit_obs_summary "Obs verification completed."
}

rollback_action() {
    bt_emit_plane_summary "obs" "${BT_STATUS_FAIL}" "Obs rollback is not implemented." "Obs rollback must remain explicit and separate from host rollback."
    exit 1
}

case "${ACTION}" in
    prepare) prepare_action ;;
    apply) apply_action ;;
    verify) verify_action ;;
    rollback) rollback_action ;;
    *)
        bt_die "Unsupported bootstrap-obs action: ${ACTION}"
        ;;
esac
