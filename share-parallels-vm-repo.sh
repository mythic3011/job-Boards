#!/usr/bin/env bash

set -euo pipefail

VM_ID="${PARALLELS_VM_ID:-}"
HOST_PATH="${PARALLELS_SHARE_HOST_PATH:-$(pwd)}"
SHARE_NAME="${PARALLELS_SHARE_NAME:-}"
PRINT_ONLY=0

usage() {
    cat <<EOF
Usage: $0 [options]

Options:
  --vm-id <id>         Parallels VM identifier; if omitted, auto-detect
  --host-path <path>   Host path to expose inside the VM; defaults to current directory
  --share-name <name>  Shared-folder name; defaults to a sanitized host-path basename
  --print-only         Print the computed VM mount path without changing VM config
  -h, --help           Show this help

Environment overrides:
  PARALLELS_VM_ID
  PARALLELS_SHARE_HOST_PATH
  PARALLELS_SHARE_NAME

Auto-detection:
  1. use --vm-id / PARALLELS_VM_ID if provided
  2. otherwise select the only running VM
  3. otherwise select the only registered VM
  4. otherwise fail loudly and require --vm-id
EOF
}

require_cmd() {
    local cmd
    for cmd in "$@"; do
        command -v "${cmd}" >/dev/null 2>&1 || {
            printf 'ERROR: missing required command: %s\n' "${cmd}" >&2
            exit 1
        }
    done
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --vm-id)
                VM_ID="$2"
                shift 2
                ;;
            --host-path)
                HOST_PATH="$2"
                shift 2
                ;;
            --share-name)
                SHARE_NAME="$2"
                shift 2
                ;;
            --print-only)
                PRINT_ONLY=1
                shift
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            *)
                printf 'ERROR: unknown argument: %s\n' "$1" >&2
                usage >&2
                exit 1
                ;;
        esac
    done
}

sanitize_share_name() {
    local raw="$1"
    printf '%s\n' "${raw}" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9._-]+/-/g; s/^-+//; s/-+$//'
}

auto_detect_vm_id() {
    local listing
    listing="$(prlctl list --all --json)"

    python3 - "${listing}" <<'PY'
import json
import sys

data = json.loads(sys.argv[1])
running = []
all_vms = []

for item in data:
    vm_id = item.get("ID") or item.get("Uuid") or item.get("uuid")
    if not vm_id:
        continue
    all_vms.append(vm_id)
    state = str(item.get("State") or item.get("state") or item.get("status") or "").lower()
    if state == "running":
        running.append(vm_id)

if len(running) == 1:
    print(running[0])
    raise SystemExit(0)

if len(all_vms) == 1:
    print(all_vms[0])
    raise SystemExit(0)

raise SystemExit(1)
PY
}

resolve_vm_id() {
    if [[ -n "${VM_ID}" ]]; then
        return 0
    fi

    if VM_ID="$(auto_detect_vm_id)"; then
        return 0
    fi

    printf 'ERROR: unable to auto-detect a unique Parallels VM. Pass --vm-id explicitly.\n' >&2
    exit 1
}

main() {
    parse_args "$@"
    require_cmd prlctl python3

    HOST_PATH="$(cd "${HOST_PATH}" && pwd)"
    [[ -d "${HOST_PATH}" ]] || {
        printf 'ERROR: host path does not exist: %s\n' "${HOST_PATH}" >&2
        exit 1
    }

    if [[ -z "${SHARE_NAME}" ]]; then
        SHARE_NAME="$(sanitize_share_name "$(basename "${HOST_PATH}")")"
    fi
    [[ -n "${SHARE_NAME}" ]] || {
        printf 'ERROR: computed share name is empty for host path: %s\n' "${HOST_PATH}" >&2
        exit 1
    }

    local vm_mount_path="/media/psf/${SHARE_NAME}"

    if [[ "${PRINT_ONLY}" == "1" ]]; then
        printf '%s\n' "${vm_mount_path}"
        exit 0
    fi

    resolve_vm_id
    prlctl set "${VM_ID}" --shf-host-add "${SHARE_NAME}" --path "${HOST_PATH}"

    printf 'Configured Parallels shared folder.\n'
    printf 'VM ID: %s\n' "${VM_ID}"
    printf 'Host path: %s\n' "${HOST_PATH}"
    printf 'Share name: %s\n' "${SHARE_NAME}"
    printf 'VM mount path: %s\n' "${vm_mount_path}"
}

main "$@"
