#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/lib/common.sh
source "${ROOT_DIR}/ops/lib/common.sh"

MODE="host"
preflight_statuses=()
REPO_CONTRACT_MESSAGE=""
REPO_CONTRACT_REMEDIATION=""

usage() {
    cat <<EOF
Usage: $0 [host|app|obs|full|verify|rollback] [options]

Options:
  --dry-run
  --compose-app-file <path>
  --compose-obs-file <path>
  --mgmt-ssh-cidr <CIDR>
  --allow-ssh-anywhere-for-demo
  --external-ingress-nic <ifname>
  --ssh-allow-user <user>
EOF
}

parse_args() {
    local positional=()

    while [[ $# -gt 0 ]]; do
        case "$1" in
            host|app|obs|full|verify|rollback)
                positional+=("$1")
                shift
                ;;
            --dry-run)
                BT_DRY_RUN=1
                shift
                ;;
            --compose-app-file)
                BT_COMPOSE_APP_FILE="$2"
                shift 2
                ;;
            --compose-obs-file)
                BT_COMPOSE_OBS_FILE="$2"
                shift 2
                ;;
            --mgmt-ssh-cidr)
                BT_MGMT_SSH_CIDR="$2"
                shift 2
                ;;
            --allow-ssh-anywhere-for-demo)
                BT_ALLOW_SSH_ANYWHERE_FOR_DEMO=1
                shift
                ;;
            --external-ingress-nic)
                BT_EXTERNAL_INGRESS_NIC="$2"
                shift 2
                ;;
            --ssh-allow-user)
                BT_SSH_ALLOW_USER="$2"
                shift 2
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            *)
                bt_die "Unknown argument: $1"
                ;;
        esac
    done

    if [[ "${#positional[@]}" -gt 1 ]]; then
        bt_die "Only one mode may be provided."
    fi

    if [[ "${#positional[@]}" -eq 1 ]]; then
        MODE="${positional[0]}"
    fi
}

run_and_capture() {
    local output_file="$1"
    shift

    if "$@" >"${output_file}"; then
        cat "${output_file}"
        return 0
    fi

    cat "${output_file}"
    return 1
}

extract_plane_summary_status() {
    local output_file="$1"
    local plane="$2"

    python3 - "${output_file}" "${plane}" <<'PY'
import json
import sys

path, plane = sys.argv[1:]
status = "SKIPPED"
with open(path, "r", encoding="utf-8") as handle:
    for line in handle:
        line = line.strip()
        if not line:
            continue
        record = json.loads(line)
        if record.get("record_type") == "plane_summary" and record.get("plane") == plane:
            status = record.get("status", status)
print(status)
PY
}

emit_skipped_plane() {
    local plane="$1"
    local message="$2"
    bt_emit_plane_summary "${plane}" "${BT_STATUS_SKIPPED}" "${message}" "Out of scope for the active mode."
}

compose_any_service_running() {
    local compose_file="$1"
    shift
    local service
    for service in "$@"; do
        if bt_compose_service_running "${compose_file}" "${service}"; then
            return 0
        fi
    done
    return 1
}

emit_overall_from_statuses() {
    local statuses=("$@")
    local overall
    overall="$(bt_aggregate_statuses "${statuses[@]}")"
    bt_emit_overall_summary "${overall}" "Overall runner status for mode ${MODE}." "Inspect plane summaries for details."
    [[ "${overall}" != "${BT_STATUS_FAIL}" ]]
}

emit_preflight_check() {
    local check_id="$1"
    local status="$2"
    local message="$3"
    local remediation="$4"
    bt_emit_check "${check_id}" "overall" "${status}" "${message}" "${remediation}"
    preflight_statuses+=("${status}")
}

run_preflight_check() {
    local check_id="$1"
    local message="$2"
    local remediation="$3"
    shift 3

    if "$@"; then
        emit_preflight_check "${check_id}" "${BT_STATUS_PASS}" "${message}" "${remediation}"
        return 0
    fi

    emit_preflight_check "${check_id}" "${BT_STATUS_FAIL}" "${message}" "${remediation}"
    return 1
}

verify_docker_available() {
    command -v docker >/dev/null 2>&1
}

verify_git_available() {
    command -v git >/dev/null 2>&1
}

verify_linux_kernel_support() {
    [[ "$(uname -s)" == "Linux" ]] || return 1
    bt_kernel_version_ge "5.12.0"
}

repo_contract_paths_for_root() {
    local root_dir="$1"
    cat <<EOF
${root_dir}/setup-blue-team-vm.sh
${root_dir}/ops/lib/common.sh
${root_dir}/ops/bootstrap/bootstrap-host.sh
${root_dir}/ops/bootstrap/bootstrap-app.sh
${root_dir}/ops/bootstrap/bootstrap-obs.sh
${root_dir}/compose.app.yml
${root_dir}/compose.obs.yml
EOF
}

verify_repo_contract_for_root() {
    local root_dir="$1"
    local required=(
        "${root_dir}/setup-blue-team-vm.sh"
        "${root_dir}/ops/lib/common.sh"
        "${root_dir}/ops/bootstrap/bootstrap-host.sh"
        "${root_dir}/ops/bootstrap/bootstrap-app.sh"
        "${root_dir}/ops/bootstrap/bootstrap-obs.sh"
        "${root_dir}/compose.app.yml"
        "${root_dir}/compose.obs.yml"
    )
    local path

    for path in "${required[@]}"; do
        [[ -e "${path}" ]] || return 1
    done

    return 0
}

adopt_root_dir() {
    local new_root="$1"
    local old_root="${ROOT_DIR}"

    ROOT_DIR="${new_root}"
    BT_ROOT_DIR="${new_root}"

    if [[ "${BT_COMPOSE_APP_FILE}" == "${old_root}/compose.app.yml" ]]; then
        BT_COMPOSE_APP_FILE="${new_root}/compose.app.yml"
    fi
    if [[ "${BT_COMPOSE_OBS_FILE}" == "${old_root}/compose.obs.yml" ]]; then
        BT_COMPOSE_OBS_FILE="${new_root}/compose.obs.yml"
    fi
}

repo_search_roots() {
    local hints="${BT_PROJECT_ROOT_HINTS:-}"
    local root

    if [[ -n "${BT_PROJECT_ROOT_OVERRIDE:-}" ]]; then
        printf '%s\n' "${BT_PROJECT_ROOT_OVERRIDE}"
    fi

    printf '%s\n' "${PWD}"
    printf '%s\n' "$(dirname "${PWD}")"

    if [[ -n "${hints}" ]]; then
        while IFS= read -r root; do
            [[ -n "${root}" ]] && printf '%s\n' "${root}"
        done < <(printf '%s' "${hints}" | tr ':' '\n')
    fi

    cat <<'EOF'
/media/psf
/mnt
/srv
/opt
/workspace
/workspaces
/home
/root
EOF
}

search_repo_contract_root() {
    local candidate
    local setup_path
    local search_depth="${BT_PROJECT_ROOT_SEARCH_DEPTH:-6}"

    if verify_repo_contract_for_root "${ROOT_DIR}"; then
        printf '%s\n' "${ROOT_DIR}"
        return 0
    fi

    while IFS= read -r candidate; do
        [[ -n "${candidate}" && -d "${candidate}" ]] || continue

        if verify_repo_contract_for_root "${candidate}"; then
            printf '%s\n' "${candidate}"
            return 0
        fi

        while IFS= read -r setup_path; do
            [[ -n "${setup_path}" ]] || continue
            candidate="$(dirname "${setup_path}")"
            if verify_repo_contract_for_root "${candidate}"; then
                printf '%s\n' "${candidate}"
                return 0
            fi
        done < <(find "${candidate}" -maxdepth "${search_depth}" -type f -name 'setup-blue-team-vm.sh' 2>/dev/null)
    done < <(repo_search_roots | awk '!seen[$0]++')

    if [[ -n "${BT_AUTO_CLONE_REMOTE_URL:-}" ]]; then
        local clone_dir="${BT_AUTO_CLONE_DIR:-${HOME:-/root}/job-Boards}"
        if [[ ! -d "${clone_dir}/.git" ]]; then
            git clone --depth 1 "${BT_AUTO_CLONE_REMOTE_URL}" "${clone_dir}" >/dev/null 2>&1 || true
        fi
        if verify_repo_contract_for_root "${clone_dir}"; then
            printf '%s\n' "${clone_dir}"
            return 0
        fi
    fi

    return 1
}

verify_repo_contract() {
    local resolved_root

    if ! resolved_root="$(search_repo_contract_root)"; then
        REPO_CONTRACT_MESSAGE="Repo-managed bootstrap surfaces could not be resolved in this VM workspace."
        REPO_CONTRACT_REMEDIATION="Provide BT_PROJECT_ROOT_HINTS, mount the repo into the VM, or set BT_AUTO_CLONE_REMOTE_URL for auto-clone."
        return 1
    fi

    if [[ "${resolved_root}" != "${ROOT_DIR}" ]]; then
        adopt_root_dir "${resolved_root}"
        REPO_CONTRACT_MESSAGE="Repo-managed bootstrap surfaces were auto-resolved at ${resolved_root}."
        REPO_CONTRACT_REMEDIATION="No action required."
    else
        REPO_CONTRACT_MESSAGE="Repo-managed bootstrap surfaces are present in the current VM workspace."
        REPO_CONTRACT_REMEDIATION="No action required."
    fi

    return 0
}

resolve_repo_entrypoint() {
    if verify_repo_contract; then
        return 0
    fi

    bt_emit_check "overall.runtime.repo_contract" "overall" "${BT_STATUS_FAIL}" "${REPO_CONTRACT_MESSAGE}" "${REPO_CONTRACT_REMEDIATION}"
    bt_emit_overall_summary "${BT_STATUS_FAIL}" "Runner entrypoint could not resolve the repo workspace." "Resolve repo discovery before rerunning."
    exit 1
}

run_preflight() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        return 0
    fi

    preflight_statuses=()

    run_preflight_check "overall.runtime.docker_available" "Docker CLI is available in this runtime." "Install Docker in the target VM before running live bootstrap." verify_docker_available || true
    run_preflight_check "overall.runtime.git_available" "Git is available in this runtime." "Install Git in the target VM before running live bootstrap." verify_git_available || true
    run_preflight_check "overall.runtime.kernel_read_only_support" "Linux kernel is new enough for read-only mount validation." "Use a Linux VM with kernel 5.12 or newer for live smoke assertions." verify_linux_kernel_support || true
    if verify_repo_contract; then
        emit_preflight_check "overall.runtime.repo_contract" "${BT_STATUS_PASS}" "${REPO_CONTRACT_MESSAGE}" "${REPO_CONTRACT_REMEDIATION}"
    else
        emit_preflight_check "overall.runtime.repo_contract" "${BT_STATUS_FAIL}" "${REPO_CONTRACT_MESSAGE}" "${REPO_CONTRACT_REMEDIATION}"
    fi

    local preflight_status
    preflight_status="$(bt_aggregate_statuses "${preflight_statuses[@]}")"
    if [[ "${preflight_status}" == "${BT_STATUS_FAIL}" ]]; then
        bt_emit_overall_summary "${BT_STATUS_FAIL}" "Runner preflight failed before mode ${MODE}." "Resolve failed runtime prerequisites before rerunning."
        exit 1
    fi
}

host_mode() {
    local tmp
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" \
    BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" \
    BT_MGMT_SSH_CIDR="${BT_MGMT_SSH_CIDR:-}" \
    BT_ALLOW_SSH_ANYWHERE_FOR_DEMO="${BT_ALLOW_SSH_ANYWHERE_FOR_DEMO:-0}" \
    BT_EXTERNAL_INGRESS_NIC="${BT_EXTERNAL_INGRESS_NIC:-}" \
    BT_SSH_ALLOW_USER="${BT_SSH_ALLOW_USER:-}" \
    BT_DRY_RUN="${BT_DRY_RUN}" \
    run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-host.sh" apply

    local host_status
    host_status="$(extract_plane_summary_status "${tmp}" "host")"
    emit_overall_from_statuses "${host_status}"
}

verify_mode() {
    local tmp tmp_app tmp_obs
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" \
    BT_DRY_RUN="${BT_DRY_RUN}" \
    run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-host.sh" verify || true

    local host_status
    host_status="$(extract_plane_summary_status "${tmp}" "host")"
    local app_status="${BT_STATUS_SKIPPED}"
    local obs_status="${BT_STATUS_SKIPPED}"

    if compose_any_service_running "${BT_COMPOSE_APP_FILE}" nginx laravel.test postgres redis crowdsec; then
        tmp_app="$(mktemp)"
        BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" run_and_capture "${tmp_app}" "${ROOT_DIR}/ops/bootstrap/bootstrap-app.sh" verify || true
        app_status="$(extract_plane_summary_status "${tmp_app}" "app")"
        rm -f "${tmp_app}"
    else
        emit_skipped_plane "app" "App verify is skipped because the app plane is not running."
    fi

    if compose_any_service_running "${BT_COMPOSE_OBS_FILE}" grafana loki promtail prometheus auth-service; then
        tmp_obs="$(mktemp)"
        BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" run_and_capture "${tmp_obs}" "${ROOT_DIR}/ops/bootstrap/bootstrap-obs.sh" verify || true
        obs_status="$(extract_plane_summary_status "${tmp_obs}" "obs")"
        rm -f "${tmp_obs}"
    else
        emit_skipped_plane "obs" "Obs verify is skipped because the obs plane is not running."
    fi

    emit_overall_from_statuses "${host_status}" "${app_status}" "${obs_status}"
}

rollback_mode() {
    local tmp
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-host.sh" rollback
    local host_status
    host_status="$(extract_plane_summary_status "${tmp}" "host")"
    emit_overall_from_statuses "${host_status}"
}

app_mode() {
    local tmp
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-app.sh" apply
    local app_status
    app_status="$(extract_plane_summary_status "${tmp}" "app")"
    emit_skipped_plane "host" "Host plane is not executed by app mode."
    emit_skipped_plane "obs" "Obs plane is out of scope for app mode."
    emit_overall_from_statuses "${BT_STATUS_SKIPPED}" "${app_status}" "${BT_STATUS_SKIPPED}"
}

obs_mode() {
    local tmp
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-obs.sh" apply
    local obs_status
    obs_status="$(extract_plane_summary_status "${tmp}" "obs")"
    emit_skipped_plane "host" "Host plane is not executed by obs mode."
    emit_skipped_plane "app" "App plane is out of scope for obs mode."
    emit_overall_from_statuses "${BT_STATUS_SKIPPED}" "${BT_STATUS_SKIPPED}" "${obs_status}"
}

full_mode() {
    local tmp_host tmp_app tmp_obs
    tmp_host="$(mktemp)"
    tmp_app="$(mktemp)"
    tmp_obs="$(mktemp)"
    trap 'rm -f "${tmp_host}" "${tmp_app}" "${tmp_obs}"' RETURN

    BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" \
    BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" \
    BT_MGMT_SSH_CIDR="${BT_MGMT_SSH_CIDR:-}" \
    BT_ALLOW_SSH_ANYWHERE_FOR_DEMO="${BT_ALLOW_SSH_ANYWHERE_FOR_DEMO:-0}" \
    BT_EXTERNAL_INGRESS_NIC="${BT_EXTERNAL_INGRESS_NIC:-}" \
    BT_SSH_ALLOW_USER="${BT_SSH_ALLOW_USER:-}" \
    BT_DRY_RUN="${BT_DRY_RUN}" \
    run_and_capture "${tmp_host}" "${ROOT_DIR}/ops/bootstrap/bootstrap-host.sh" apply

    BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp_app}" "${ROOT_DIR}/ops/bootstrap/bootstrap-app.sh" apply
    BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp_obs}" "${ROOT_DIR}/ops/bootstrap/bootstrap-obs.sh" apply

    local host_status app_status obs_status
    host_status="$(extract_plane_summary_status "${tmp_host}" "host")"
    app_status="$(extract_plane_summary_status "${tmp_app}" "app")"
    obs_status="$(extract_plane_summary_status "${tmp_obs}" "obs")"

    emit_overall_from_statuses "${host_status}" "${app_status}" "${obs_status}"
}

parse_args "$@"
resolve_repo_entrypoint
run_preflight

case "${MODE}" in
    host) host_mode ;;
    app) app_mode ;;
    obs) obs_mode ;;
    full) full_mode ;;
    verify) verify_mode ;;
    rollback) rollback_mode ;;
    *)
        usage
        exit 1
        ;;
esac
