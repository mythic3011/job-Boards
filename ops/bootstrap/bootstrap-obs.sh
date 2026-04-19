#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
OBS_WAIT_TIMEOUT_SECONDS="${BT_OBS_WAIT_TIMEOUT_SECONDS:-90}"
OBS_GENERATED_ENV_FILE="${BT_OBS_GENERATED_ENV_FILE:-${BT_RUNTIME_DIR}/obs.generated.env}"
OBS_GENERATED_AUDIT_FILE="${BT_OBS_GENERATED_AUDIT_FILE:-${BT_RUNTIME_DIR}/obs.generated-secrets.jsonl}"
OBS_RENDERED_DIR="${BT_OBS_RENDERED_DIR:-${BT_STATE_DIR}/rendered}"
OBS_GRAFANA_ADMIN_SECRET_FILE="${BT_GRAFANA_ADMIN_SECRET_FILE:-${BT_RUNTIME_DIR}/grafana-admin-secret}"
OBS_PROMETHEUS_WEB_CONFIG_FILE="${BT_PROMETHEUS_WEB_CONFIG_FILE:-${OBS_RENDERED_DIR}/prometheus.web-config.yml}"
OBS_GRAFANA_DATASOURCE_TEMPLATE_FILE="${BT_OBS_GRAFANA_DATASOURCE_TEMPLATE_FILE:-${REPO_ROOT}/docker/grafana/provisioning/datasources/datasources.yaml}"
OBS_GRAFANA_DATASOURCES_FILE="${BT_OBS_GRAFANA_DATASOURCES_FILE:-${OBS_RENDERED_DIR}/grafana.datasources.yml}"
OBS_GRAFANA_SQLITE_HELPER_IMAGE="${BT_OBS_GRAFANA_SQLITE_HELPER_IMAGE:-python:3.12-alpine}"
obs_statuses=()

run_obs_check() {
    local check_id="$1" message="$2" remediation="$3"
    shift 3
    bt_run_plane_check "obs" obs_statuses "${check_id}" "${BT_STATUS_PASS}" "${message}" "${remediation}" "$@"
}

emit_obs_summary() {
    bt_emit_plane_check_summary "obs" obs_statuses "$1"
}

obs_check_id_suffix() {
    printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]'
}

verify_obs_logs() {
    bt_compose "${BT_COMPOSE_OBS_FILE}" exec -T promtail sh -c 'test -r /var/log/nginx/access.log || test -r /var/log/nginx/error.log'
}

ensure_obs_app_plane_network() {
    bt_ensure_app_plane_network
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
        "DB_DATABASE"
        "DB_USERNAME"
        "CANONICAL_AUDIT_AUTH_SERVICE_SECRET"
        "GRAFANA_POSTGRES_SECRET"
        "GRAFANA_ADMIN_SECRET_FILE"
        "PROMETHEUS_PASSWORD_HASH"
        "PROMETHEUS_WEB_CONFIG_FILE"
        "GRAFANA_DATASOURCES_FILE"
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
            GRAFANA_ADMIN_SECRET_FILE|PROMETHEUS_WEB_CONFIG_FILE)
                if [[ ! -r "${value}" ]]; then
                    missing+=("${var_name}")
                fi
                ;;
            GRAFANA_DATASOURCES_FILE)
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

obs_grafana_managed_datasources_json() {
    python3 - "${OBS_GRAFANA_DATASOURCE_TEMPLATE_FILE}" <<'PY'
import json
import re
import sys

path = sys.argv[1]
managed = []
current = None
in_datasources = False

with open(path, encoding="utf-8") as handle:
    for raw_line in handle:
        line = raw_line.rstrip("\n")

        if line == "datasources:":
            in_datasources = True
            continue

        if not in_datasources:
            continue

        match = re.match(r"^  - name:\s*(.+?)\s*$", line)
        if match:
            if current is not None:
                managed.append(current)
            current = {"name": match.group(1), "orgId": 1, "uid": None}
            continue

        if current is None:
            continue

        match = re.match(r"^    orgId:\s*(\d+)\s*$", line)
        if match:
            current["orgId"] = int(match.group(1))
            continue

        match = re.match(r"^    uid:\s*(.+?)\s*$", line)
        if match:
            current["uid"] = match.group(1)

if current is not None:
    managed.append(current)

if not managed or any(item.get("uid") in (None, "") for item in managed):
    raise SystemExit("grafana datasource template must declare managed names and uids")

print(json.dumps(managed, separators=(",", ":")))
PY
}

obs_grafana_runtime_volume_name() {
    local helper_script

    helper_script="$(cat <<'PY'
import json
import sys

config = json.load(sys.stdin)
grafana_service = config.get("services", {}).get("grafana", {})
grafana_volume_key = None

for volume in grafana_service.get("volumes", []):
    if volume.get("type") == "volume" and volume.get("target") == "/var/lib/grafana":
        grafana_volume_key = volume.get("source")
        break

if not grafana_volume_key:
    raise SystemExit(1)

volume_config = config.get("volumes", {}).get(grafana_volume_key, {})
print(volume_config.get("name") or grafana_volume_key, end="")
PY
)"

    bt_preload_compose_env
    env "GRAFANA_DATASOURCES_FILE=${OBS_GRAFANA_DATASOURCES_FILE}" \
        docker compose -f "${BT_COMPOSE_OBS_FILE}" config --format json | python3 -c "${helper_script}"
}

obs_grafana_runtime_mountpoint() {
    local volume_name="$1"
    docker volume inspect -f '{{ .Mountpoint }}' "${volume_name}" 2>/dev/null || true
}

obs_detect_grafana_datasource_aliases_from_db_path() {
    local db_path="$1"
    local managed_json="$2"

    python3 - "${db_path}" "${managed_json}" <<'PY'
import json
import os
import sqlite3
import sys

path = sys.argv[1]
managed = json.loads(sys.argv[2])

if not os.path.exists(path) or os.path.getsize(path) == 0:
    print("[]")
    raise SystemExit(0)

connection = sqlite3.connect(f"file:{path}?mode=ro", uri=True)
connection.row_factory = sqlite3.Row
tables = {
    row["name"]
    for row in connection.execute("select name from sqlite_master where type = ?", ("table",))
}

if "data_source" not in tables:
    print("[]")
    raise SystemExit(0)

managed_by_uid = {item["uid"]: item for item in managed}
query = "select org_id, name, uid from data_source where uid in ({})".format(
    ",".join("?" for _ in managed_by_uid)
)
rows = connection.execute(query, tuple(managed_by_uid)).fetchall()

aliases = []
seen = set()

for row in rows:
    managed_item = managed_by_uid.get(row["uid"])
    if managed_item is None:
        continue

    org_id = int(row["org_id"] or 1)
    if org_id != int(managed_item.get("orgId", 1)):
        continue

    if row["name"] == managed_item["name"]:
        continue

    key = (org_id, row["name"])
    if key in seen:
        continue

    seen.add(key)
    aliases.append({"orgId": org_id, "name": row["name"], "uid": row["uid"]})

aliases.sort(key=lambda item: (item["orgId"], item["name"]))
print(json.dumps(aliases, separators=(",", ":")))
PY
}

obs_detect_grafana_datasource_aliases() {
    local managed_json="$1"
    local volume_name
    local mountpoint
    local db_path
    local detected_json
    local helper_script

    volume_name="$(obs_grafana_runtime_volume_name 2>/dev/null || true)"
    if [[ -z "${volume_name}" ]]; then
        printf '[]'
        return 0
    fi

    if ! docker volume inspect "${volume_name}" >/dev/null 2>&1; then
        printf '[]'
        return 0
    fi

    mountpoint="$(obs_grafana_runtime_mountpoint "${volume_name}")"
    db_path="${mountpoint%/}/grafana.db"

    if [[ -n "${mountpoint}" && -r "${db_path}" ]]; then
        detected_json="$(obs_detect_grafana_datasource_aliases_from_db_path "${db_path}" "${managed_json}")" || {
            bt_warn "Unable to inspect Grafana datasource aliases from host-accessible volume mountpoint ${db_path}; falling back to containerized inspection."
            detected_json=""
        }

        if [[ -n "${detected_json}" ]]; then
            printf '%s' "${detected_json}"
            return 0
        fi
    fi

    helper_script="$(cat <<'PY'
import json
import os
import sqlite3

path = "/grafana/grafana.db"
managed = json.loads(os.environ["MANAGED_DATASOURCES_JSON"])

if not os.path.exists(path) or os.path.getsize(path) == 0:
    print("[]")
    raise SystemExit(0)

connection = sqlite3.connect(f"file:{path}?mode=ro", uri=True)
connection.row_factory = sqlite3.Row
tables = {
    row["name"]
    for row in connection.execute("select name from sqlite_master where type = ?", ("table",))
}

if "data_source" not in tables:
    print("[]")
    raise SystemExit(0)

managed_by_uid = {item["uid"]: item for item in managed}
query = "select org_id, name, uid from data_source where uid in ({})".format(
    ",".join("?" for _ in managed_by_uid)
)
rows = connection.execute(query, tuple(managed_by_uid)).fetchall()

aliases = []
seen = set()

for row in rows:
    managed_item = managed_by_uid.get(row["uid"])
    if managed_item is None:
        continue

    org_id = int(row["org_id"] or 1)
    if org_id != int(managed_item.get("orgId", 1)):
        continue

    if row["name"] == managed_item["name"]:
        continue

    key = (org_id, row["name"])
    if key in seen:
        continue

    seen.add(key)
    aliases.append({"orgId": org_id, "name": row["name"], "uid": row["uid"]})

aliases.sort(key=lambda item: (item["orgId"], item["name"]))
print(json.dumps(aliases, separators=(",", ":")))
PY
)"

    detected_json="$(
        MANAGED_DATASOURCES_JSON="${managed_json}" docker run --rm \
            -e MANAGED_DATASOURCES_JSON \
            -v "${volume_name}:/grafana:ro" \
            "${OBS_GRAFANA_SQLITE_HELPER_IMAGE}" \
            python3 -c "${helper_script}"
    )" || {
        bt_warn "Unable to inspect existing Grafana datasource aliases from Docker volume ${volume_name}."
        return 1
    }

    printf '%s' "${detected_json:-[]}"
}

obs_render_grafana_datasources_config() {
    local managed_json
    local detected_aliases_json
    local current_path=""

    managed_json="$(obs_grafana_managed_datasources_json)" || return 1
    detected_aliases_json="$(obs_detect_grafana_datasource_aliases "${managed_json}")" || detected_aliases_json='[]'

    python3 - "${OBS_GRAFANA_DATASOURCE_TEMPLATE_FILE}" "${OBS_GRAFANA_DATASOURCES_FILE}" "${managed_json}" "${detected_aliases_json}" <<'PY'
import json
import pathlib
import sys

template_path = pathlib.Path(sys.argv[1])
output_path = pathlib.Path(sys.argv[2])
managed = json.loads(sys.argv[3])
aliases = json.loads(sys.argv[4])
template = template_path.read_text(encoding="utf-8")

api_prefix = "apiVersion: 1\n\n"
if not template.startswith(api_prefix):
    raise SystemExit("grafana datasource template must start with 'apiVersion: 1'")

delete_entries = []
seen = set()
for item in [*managed, *aliases]:
    key = (int(item.get("orgId", 1)), item["name"])
    if key in seen:
        continue
    seen.add(key)
    delete_entries.append(key)

lines = ["deleteDatasources:"]
for org_id, name in delete_entries:
    lines.append(f"  - name: {json.dumps(name)}")
    lines.append(f"    orgId: {org_id}")
lines.extend(["", "prune: true", ""])

output_path.write_text(api_prefix + "\n".join(lines) + template[len(api_prefix):], encoding="utf-8")
PY

    if [[ "${BT_DRY_RUN}" != "1" ]]; then
        chmod 0644 "${OBS_GRAFANA_DATASOURCES_FILE}"
    fi

    current_path="$(obs_effective_env_value "GRAFANA_DATASOURCES_FILE" || true)"
    obs_set_generated_value "GRAFANA_DATASOURCES_FILE" "${OBS_GRAFANA_DATASOURCES_FILE}"
    if [[ "${current_path}" != "${OBS_GRAFANA_DATASOURCES_FILE}" ]]; then
        obs_emit_generated_audit "GRAFANA_DATASOURCES_FILE" "grafana_datasource_template" "rendered_runtime_config" "true" "false"
    fi
}

obs_generated_env_value() {
    local key="$1"
    bt_env_file_value "${OBS_GENERATED_ENV_FILE}" "${key}"
}

obs_requested_env_value() {
    local key="$1"
    bt_env_value "${key}" || obs_generated_env_value "${key}"
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
    obs_generated_env_value "${key}" || bt_env_value "${key}"
}

obs_materialize_secret_file() {
    local target_key="$1"
    local default_path="$2"
    shift 2
    local current_path
    local desired_path
    local source_value
    local check_suffix
    local source_key=""
    local candidate_key

    current_path="$(obs_generated_env_value "${target_key}" || true)"
    desired_path="$(bt_env_value "${target_key}" || true)"
    if [[ -z "${desired_path}" ]]; then
        desired_path="${current_path:-${default_path}}"
    fi

    source_value=""
    for candidate_key in "$@"; do
        source_value="$(bt_env_value "${candidate_key}" || true)"
        [[ -n "${source_value}" ]] || continue
        source_key="${candidate_key}"
        break
    done
    if [[ -z "${source_key}" ]]; then
        for candidate_key in "$@"; do
            source_value="$(obs_generated_env_value "${candidate_key}" || true)"
            [[ -n "${source_value}" ]] || continue
            source_key="${candidate_key}"
            break
        done
    fi

    if [[ -z "${source_key}" || -z "${source_value}" ]]; then
        [[ -r "${desired_path}" ]] || return 1
        if [[ "${current_path}" != "${desired_path}" ]]; then
            obs_set_generated_value "${target_key}" "${desired_path}"
        fi
        return 0
    fi

    if [[ -r "${desired_path}" ]] && [[ "$(cat "${desired_path}")" == "${source_value}" ]]; then
        if [[ "${current_path}" != "${desired_path}" ]]; then
            obs_set_generated_value "${target_key}" "${desired_path}"
        fi
        return 0
    fi

    bt_write_file "${desired_path}" "${source_value}"
    if [[ "${BT_DRY_RUN}" != "1" ]]; then
        chmod 0600 "${desired_path}"
    fi

    obs_set_generated_value "${target_key}" "${desired_path}"
    obs_emit_generated_audit "${target_key}" "${source_key}" "materialized_secret_file" "true" "false"
    check_suffix="$(obs_check_id_suffix "${target_key}")"
    bt_emit_check "obs.bootstrap.${check_suffix}.generated" "obs" "${BT_STATUS_PASS}" "${target_key} was materialized from ${source_key}." "Review generated env provenance if operator-managed rotation is required."
    obs_statuses+=("${BT_STATUS_PASS}")
    return 0
}

obs_persist_runtime_input() {
    local target_key="$1"
    shift

    local current
    current="$(obs_generated_env_value "${target_key}" || true)"

    local source_key=""
    local candidate_key
    local source_value=""
    local check_suffix
    for candidate_key in "$@"; do
        source_value="$(bt_env_value "${candidate_key}" || true)"
        [[ -n "${source_value}" ]] || continue
        source_key="${candidate_key}"
        break
    done
    if [[ -z "${source_key}" ]]; then
        for candidate_key in "$@"; do
            source_value="$(obs_generated_env_value "${candidate_key}" || true)"
            [[ -n "${source_value}" ]] || continue
            source_key="${candidate_key}"
            break
        done
    fi

    if [[ -z "${source_key}" || -z "${source_value}" ]]; then
        return 1
    fi

    if [[ "${current}" == "${source_value}" ]]; then
        return 0
    fi

    obs_set_generated_value "${target_key}" "${source_value}"
    obs_emit_generated_audit "${target_key}" "${source_key}" "persisted_runtime_input" "true" "false"
    check_suffix="$(obs_check_id_suffix "${target_key}")"
    bt_emit_check "obs.bootstrap.${check_suffix}.persisted" "obs" "${BT_STATUS_PASS}" "${target_key} was persisted into obs runtime env from ${source_key}." "Review generated env provenance if operator-managed rotation is required."
    obs_statuses+=("${BT_STATUS_PASS}")
    return 0
}

obs_autofix_session_secret() {
    local current explicit
    current="$(obs_generated_env_value "SESSION_SECRET" || true)"
    explicit="$(bt_env_value "SESSION_SECRET" || true)"
    if [[ -n "${explicit}" ]]; then
        if [[ "${current}" != "${explicit}" ]]; then
            obs_set_generated_value "SESSION_SECRET" "${explicit}"
            obs_emit_generated_audit "SESSION_SECRET" "SESSION_SECRET" "persisted_runtime_input" "true" "false"
        fi
        return 0
    fi

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

    local current explicit current_raw
    current_raw="$(obs_generated_env_value "${target_key}" || true)"
    current="$(bt_normalize_compose_dollars "${current_raw}")"
    explicit="$(bt_normalize_compose_dollars "$(bt_env_value "${target_key}" || true)")"
    if [[ -n "${explicit}" ]]; then
        bt_bcrypt_hash_runtime_usable "${explicit}" || return 1
        if [[ "${current_raw}" != "${explicit}" ]]; then
            obs_set_generated_value "${target_key}" "${explicit}"
            obs_emit_generated_audit "${target_key}" "${target_key}" "persisted_runtime_input" "true" "false"
        fi
        return 0
    fi

    local source_key=""
    local candidate_key
    local source_value=""
    local check_suffix
    for candidate_key in "$@"; do
        source_value="$(bt_env_value "${candidate_key}" || true)"
        [[ -n "${source_value}" ]] || continue
        source_key="${candidate_key}"
        break
    done
    if [[ -z "${source_key}" ]]; then
        for candidate_key in "$@"; do
            source_value="$(obs_generated_env_value "${candidate_key}" || true)"
            [[ -n "${source_value}" ]] || continue
            source_key="${candidate_key}"
            break
        done
    fi

    if [[ -z "${source_key}" || -z "${source_value}" ]]; then
        [[ -n "${current}" ]] || return 1
        bt_bcrypt_hash_runtime_usable "${current}"
        return $?
    fi

    if [[ -n "${current}" ]]; then
        bt_bcrypt_hash_runtime_usable "${current}" || return 1
        if bt_bcrypt_hash_matches "${current}" "${source_value}"; then
            return 0
        fi
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

    local failed=0
    obs_persist_runtime_input "MONITORING_ADMIN_USERNAME" "MONITORING_ADMIN_USERNAME" || failed=1
    obs_persist_runtime_input "DB_DATABASE" "DB_DATABASE" || failed=1
    obs_persist_runtime_input "DB_USERNAME" "DB_USERNAME" || failed=1
    obs_persist_runtime_input "CANONICAL_AUDIT_AUTH_SERVICE_SECRET" "CANONICAL_AUDIT_AUTH_SERVICE_SECRET" || failed=1
    obs_persist_runtime_input "GRAFANA_POSTGRES_SECRET" "GRAFANA_POSTGRES_SECRET" "DB_PASSWORD" || failed=1
    obs_autofix_session_secret || failed=1
    obs_autofix_password_hash "MONITORING_PASSWORD_HASH" "MONITORING_PASSWORD" || failed=1
    obs_autofix_password_hash "PROMETHEUS_PASSWORD_HASH" "PROMETHEUS_PASSWORD" "MONITORING_PASSWORD" || failed=1
    obs_materialize_secret_file "GRAFANA_ADMIN_SECRET_FILE" "${OBS_GRAFANA_ADMIN_SECRET_FILE}" "GRAFANA_PASSWORD" "MONITORING_PASSWORD" || failed=1
    obs_render_prometheus_web_config || failed=1
    obs_render_grafana_datasources_config || failed=1

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
    run_obs_check "obs.bootstrap.app_plane_network" "Obs bootstrap resolved or created a compatible shared app-plane network for shared auth and database traffic." "Set BT_APP_PLANE_NETWORK_NAME explicitly when reusing an existing shared app-plane network; the 172.29.0.0/24 subnet is a fixed compose contract in this stack." ensure_obs_app_plane_network || {
        emit_obs_summary "Obs bootstrap failed preflight."
        exit 1
    }

    bt_compose "${BT_COMPOSE_OBS_FILE}" up -d --build
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" auth-service healthy "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" grafana healthy "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" loki running "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" promtail running "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" prometheus healthy "${OBS_WAIT_TIMEOUT_SECONDS}" || true

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
