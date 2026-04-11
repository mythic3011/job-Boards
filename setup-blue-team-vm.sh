#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/lib/common.sh
source "${ROOT_DIR}/ops/lib/common.sh"

MODE="host"
preflight_statuses=()
REPO_CONTRACT_MESSAGE=""
REPO_CONTRACT_REMEDIATION=""
BT_LAST_RESOLVED_PLANE_STATUS=""
BT_HOST_DNS_PRIMARY="${BT_HOST_DNS_PRIMARY:-1.1.1.1}"
BT_HOST_DNS_SECONDARY="${BT_HOST_DNS_SECONDARY:-8.8.8.8}"
BT_HOST_DNS_DROPIN_PATH="${BT_HOST_DNS_DROPIN_PATH:-/etc/systemd/resolved.conf.d/blue-team-vm-dns.conf}"
BT_REQUIRED_DNS_HOSTS="${BT_REQUIRED_DNS_HOSTS:-registry-1.docker.io api.crowdsec.net}"

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
        try:
            record = json.loads(line)
        except json.JSONDecodeError:
            continue
        if record.get("record_type") == "plane_summary" and record.get("plane") == plane:
            status = record.get("status", status)
print(status)
PY
}

plane_summary_present() {
    local output_file="$1"
    local plane="$2"

    python3 - "${output_file}" "${plane}" <<'PY'
import json
import sys

path, plane = sys.argv[1:]

with open(path, "r", encoding="utf-8") as handle:
    for line in handle:
        line = line.strip()
        if not line:
            continue
        try:
            record = json.loads(line)
        except json.JSONDecodeError:
            continue
        if record.get("record_type") == "plane_summary" and record.get("plane") == plane:
            raise SystemExit(0)

raise SystemExit(1)
PY
}

resolve_plane_status_with_fallback() {
    local output_file="$1"
    local plane="$2"
    local child_exit="$3"
    local child_label="$4"

    if plane_summary_present "${output_file}" "${plane}"; then
        BT_LAST_RESOLVED_PLANE_STATUS="$(extract_plane_summary_status "${output_file}" "${plane}")"
        return 0
    fi

    local message
    if [[ "${child_exit}" -eq 0 ]]; then
        message="${child_label} completed without emitting a structured plane summary."
    else
        message="${child_label} exited before emitting a structured plane summary."
    fi

    bt_emit_plane_summary "${plane}" "${BT_STATUS_FAIL}" "${message}" "Inspect child bootstrap output and restore plane summary emission."
    BT_LAST_RESOLVED_PLANE_STATUS="${BT_STATUS_FAIL}"
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

verify_required_dns_resolution() {
    local host
    for host in ${BT_REQUIRED_DNS_HOSTS}; do
        getent hosts "${host}" >/dev/null 2>&1 || return 1
    done
    return 0
}

can_auto_fix_dns_resolution() {
    [[ "${EUID}" -eq 0 ]] || return 1
    command -v systemctl >/dev/null 2>&1 || return 1
    systemctl list-unit-files systemd-resolved.service >/dev/null 2>&1 || return 1
}

fallback_dns_servers_routable() {
    command -v ip >/dev/null 2>&1 || return 1
    ip route get "${BT_HOST_DNS_PRIMARY}" >/dev/null 2>&1 || return 1
    ip route get "${BT_HOST_DNS_SECONDARY}" >/dev/null 2>&1 || return 1
}

default_route_interface() {
    local iface
    iface="$(ip route show default 2>/dev/null | awk 'NR == 1 {for (i = 1; i <= NF; i++) if ($i == "dev") {print $(i + 1); exit}}')"
    if [[ -n "${iface}" ]]; then
        printf '%s\n' "${iface}"
        return 0
    fi

    if command -v resolvectl >/dev/null 2>&1; then
        iface="$(
            resolvectl dns 2>/dev/null | awk '
                /^Link [0-9]+ \(/ {
                    iface = $3
                    gsub(/[():]/, "", iface)
                    if (iface !~ /^(docker0|br-|veth)/) {
                        print iface
                        exit
                    }
                }
            '
        )"
        if [[ -n "${iface}" ]]; then
            printf '%s\n' "${iface}"
            return 0
        fi
    fi

    return 1
}

apply_dns_fallback_dropin() {
    local dns_values="${BT_HOST_DNS_PRIMARY} ${BT_HOST_DNS_SECONDARY}"
    local iface=""

    bt_log "Applying fallback host DNS servers: ${dns_values}"
    bt_write_file "${BT_HOST_DNS_DROPIN_PATH}" "[Resolve]
DNS=${dns_values}
Domains=~.
"

    bt_run systemctl restart systemd-resolved
    if command -v resolvectl >/dev/null 2>&1; then
        iface="$(default_route_interface || true)"
        if [[ -n "${iface}" ]]; then
            bt_run resolvectl dns "${iface}" "${BT_HOST_DNS_PRIMARY}" "${BT_HOST_DNS_SECONDARY}"
            bt_run resolvectl domain "${iface}" '~.' || true
        fi
        bt_run resolvectl flush-caches || true
    fi
}

verify_or_fix_required_dns_resolution() {
    if verify_required_dns_resolution; then
        return 0
    fi

    can_auto_fix_dns_resolution || return 1
    if ! fallback_dns_servers_routable; then
        bt_warn "Fallback DNS servers ${BT_HOST_DNS_PRIMARY} and ${BT_HOST_DNS_SECONDARY} are not routable from this VM."
        return 1
    fi
    apply_dns_fallback_dropin || return 1
    verify_required_dns_resolution
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
    local required_readable=(
        "${root_dir}/ops/lib/common.sh"
        "${root_dir}/compose.app.yml"
        "${root_dir}/compose.obs.yml"
    )
    local required_executable=(
        "${root_dir}/setup-blue-team-vm.sh"
        "${root_dir}/ops/bootstrap/bootstrap-host.sh"
        "${root_dir}/ops/bootstrap/bootstrap-app.sh"
        "${root_dir}/ops/bootstrap/bootstrap-obs.sh"
    )
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

    REPO_CONTRACT_FAILURE_DETAIL=""

    [[ -d "${root_dir}" ]] || {
        REPO_CONTRACT_FAILURE_DETAIL="${root_dir} is not a directory"
        return 1
    }
    [[ -x "${root_dir}" && -r "${root_dir}" ]] || {
        REPO_CONTRACT_FAILURE_DETAIL="${root_dir} is not accessible to the current user"
        return 1
    }

    for path in "${required[@]}"; do
        [[ -e "${path}" ]] || {
            REPO_CONTRACT_FAILURE_DETAIL="${path} is missing"
            return 1
        }
    done

    for path in "${required_readable[@]}"; do
        [[ -r "${path}" ]] || {
            REPO_CONTRACT_FAILURE_DETAIL="${path} is not readable by the current user"
            return 1
        }
    done

    for path in "${required_executable[@]}"; do
        [[ -r "${path}" && -x "${path}" ]] || {
            REPO_CONTRACT_FAILURE_DETAIL="${path} is not executable by the current user"
            return 1
        }
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
        if [[ -n "${REPO_CONTRACT_FAILURE_DETAIL:-}" ]]; then
            REPO_CONTRACT_MESSAGE="Repo-managed bootstrap surfaces failed the workspace contract: ${REPO_CONTRACT_FAILURE_DETAIL}."
        else
            REPO_CONTRACT_MESSAGE="Repo-managed bootstrap surfaces could not be resolved in this VM workspace."
        fi
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
    if [[ "${MODE}" == "app" || "${MODE}" == "obs" || "${MODE}" == "full" ]]; then
        run_preflight_check "overall.runtime.host_dns_resolution" "Host DNS resolution is ready for external image and feed dependencies." "Run as root so the runner can apply fallback DNS servers 1.1.1.1 and 8.8.8.8, or repair the VM resolver before rerunning." verify_or_fix_required_dns_resolution || true
    fi
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
    local host_rc=0
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    if ! BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" \
        BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" \
        BT_MGMT_SSH_CIDR="${BT_MGMT_SSH_CIDR:-}" \
        BT_ALLOW_SSH_ANYWHERE_FOR_DEMO="${BT_ALLOW_SSH_ANYWHERE_FOR_DEMO:-0}" \
        BT_EXTERNAL_INGRESS_NIC="${BT_EXTERNAL_INGRESS_NIC:-}" \
        BT_SSH_ALLOW_USER="${BT_SSH_ALLOW_USER:-}" \
        BT_DRY_RUN="${BT_DRY_RUN}" \
        run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-host.sh" apply; then
        host_rc=$?
    fi

    local host_status
    resolve_plane_status_with_fallback "${tmp}" "host" "${host_rc}" "Host bootstrap apply"
    host_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
    emit_overall_from_statuses "${host_status}"
}

verify_mode() {
    local tmp tmp_app tmp_obs
    local host_rc=0
    local app_rc=0
    local obs_rc=0
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    if ! BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" \
        BT_DRY_RUN="${BT_DRY_RUN}" \
        run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-host.sh" verify; then
        host_rc=$?
    fi

    local host_status
    resolve_plane_status_with_fallback "${tmp}" "host" "${host_rc}" "Host bootstrap verify"
    host_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
    local app_status="${BT_STATUS_SKIPPED}"
    local obs_status="${BT_STATUS_SKIPPED}"

    if compose_any_service_running "${BT_COMPOSE_APP_FILE}" nginx laravel.test postgres redis crowdsec; then
        tmp_app="$(mktemp)"
        if ! BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" run_and_capture "${tmp_app}" "${ROOT_DIR}/ops/bootstrap/bootstrap-app.sh" verify; then
            app_rc=$?
        fi
        resolve_plane_status_with_fallback "${tmp_app}" "app" "${app_rc}" "App bootstrap verify"
        app_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
        rm -f "${tmp_app}"
    else
        emit_skipped_plane "app" "App verify is skipped because the app plane is not running."
    fi

    if compose_any_service_running "${BT_COMPOSE_OBS_FILE}" grafana loki promtail prometheus auth-service; then
        tmp_obs="$(mktemp)"
        if ! BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" run_and_capture "${tmp_obs}" "${ROOT_DIR}/ops/bootstrap/bootstrap-obs.sh" verify; then
            obs_rc=$?
        fi
        resolve_plane_status_with_fallback "${tmp_obs}" "obs" "${obs_rc}" "Obs bootstrap verify"
        obs_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
        rm -f "${tmp_obs}"
    else
        emit_skipped_plane "obs" "Obs verify is skipped because the obs plane is not running."
    fi

    emit_overall_from_statuses "${host_status}" "${app_status}" "${obs_status}"
}

rollback_mode() {
    local tmp
    local host_rc=0
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    if ! BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-host.sh" rollback; then
        host_rc=$?
    fi
    local host_status
    resolve_plane_status_with_fallback "${tmp}" "host" "${host_rc}" "Host bootstrap rollback"
    host_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
    emit_overall_from_statuses "${host_status}"
}

app_mode() {
    local tmp
    local app_rc=0
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    if ! BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-app.sh" apply; then
        app_rc=$?
    fi
    local app_status
    resolve_plane_status_with_fallback "${tmp}" "app" "${app_rc}" "App bootstrap apply"
    app_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
    emit_skipped_plane "host" "Host plane is not executed by app mode."
    emit_skipped_plane "obs" "Obs plane is out of scope for app mode."
    emit_overall_from_statuses "${BT_STATUS_SKIPPED}" "${app_status}" "${BT_STATUS_SKIPPED}"
}

obs_mode() {
    local tmp
    local obs_rc=0
    tmp="$(mktemp)"
    trap 'rm -f "${tmp}"' RETURN

    if ! BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp}" "${ROOT_DIR}/ops/bootstrap/bootstrap-obs.sh" apply; then
        obs_rc=$?
    fi
    local obs_status
    resolve_plane_status_with_fallback "${tmp}" "obs" "${obs_rc}" "Obs bootstrap apply"
    obs_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
    emit_skipped_plane "host" "Host plane is not executed by obs mode."
    emit_skipped_plane "app" "App plane is out of scope for obs mode."
    emit_overall_from_statuses "${BT_STATUS_SKIPPED}" "${BT_STATUS_SKIPPED}" "${obs_status}"
}

full_mode() {
    local tmp_host tmp_app tmp_obs
    local host_rc=0
    local app_rc=0
    local obs_rc=0
    tmp_host="$(mktemp)"
    tmp_app="$(mktemp)"
    tmp_obs="$(mktemp)"
    trap 'rm -f "${tmp_host}" "${tmp_app}" "${tmp_obs}"' RETURN

    if ! BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" \
        BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" \
        BT_MGMT_SSH_CIDR="${BT_MGMT_SSH_CIDR:-}" \
        BT_ALLOW_SSH_ANYWHERE_FOR_DEMO="${BT_ALLOW_SSH_ANYWHERE_FOR_DEMO:-0}" \
        BT_EXTERNAL_INGRESS_NIC="${BT_EXTERNAL_INGRESS_NIC:-}" \
        BT_SSH_ALLOW_USER="${BT_SSH_ALLOW_USER:-}" \
        BT_DRY_RUN="${BT_DRY_RUN}" \
        run_and_capture "${tmp_host}" "${ROOT_DIR}/ops/bootstrap/bootstrap-host.sh" apply; then
        host_rc=$?
    fi

    if ! BT_COMPOSE_APP_FILE="${BT_COMPOSE_APP_FILE}" BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp_app}" "${ROOT_DIR}/ops/bootstrap/bootstrap-app.sh" apply; then
        app_rc=$?
    fi
    if ! BT_COMPOSE_OBS_FILE="${BT_COMPOSE_OBS_FILE}" BT_DRY_RUN="${BT_DRY_RUN}" run_and_capture "${tmp_obs}" "${ROOT_DIR}/ops/bootstrap/bootstrap-obs.sh" apply; then
        obs_rc=$?
    fi

    local host_status app_status obs_status
    resolve_plane_status_with_fallback "${tmp_host}" "host" "${host_rc}" "Host bootstrap apply"
    host_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
    resolve_plane_status_with_fallback "${tmp_app}" "app" "${app_rc}" "App bootstrap apply"
    app_status="${BT_LAST_RESOLVED_PLANE_STATUS}"
    resolve_plane_status_with_fallback "${tmp_obs}" "obs" "${obs_rc}" "Obs bootstrap apply"
    obs_status="${BT_LAST_RESOLVED_PLANE_STATUS}"

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
