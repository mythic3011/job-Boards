#!/usr/bin/env bash

set -euo pipefail

usage() {
    cat <<'EOF'
Usage: ops/demo/run-zap-baseline.sh <label> <target-url> [output-root]

Example:
  ops/demo/run-zap-baseline.sh before https://jb.mythic3011.com demo-artifacts/zap
  ops/demo/run-zap-baseline.sh after https://jb.mythic3011.com demo-artifacts/zap
EOF
}

LABEL="${1:-}"
TARGET_URL="${2:-}"
OUTPUT_ROOT="${3:-demo-artifacts/zap}"

[[ -n "${LABEL}" && -n "${TARGET_URL}" ]] || {
    usage
    exit 1
}

command -v docker >/dev/null 2>&1 || {
    printf 'ERROR: docker is required.\n' >&2
    exit 1
}

OUTPUT_DIR="${OUTPUT_ROOT}/${LABEL}"
mkdir -p "${OUTPUT_DIR}"

ZAP_BASELINE_POLICY="${ZAP_BASELINE_POLICY:-ops/demo/zap-baseline-policy.conf}"
zap_policy_args=()

if [[ -n "${ZAP_BASELINE_POLICY}" && -f "${ZAP_BASELINE_POLICY}" ]]; then
    cp "${ZAP_BASELINE_POLICY}" "${OUTPUT_DIR}/zap-baseline-policy.conf"
    zap_policy_args=(-c zap-baseline-policy.conf)
fi

target_host="$(python3 - "${TARGET_URL}" <<'PY'
from urllib.parse import urlparse
import sys

print(urlparse(sys.argv[1]).hostname or "")
PY
)"
docker_network_args=()
target_container="${ZAP_TARGET_CONTAINER:-jobs-boards-nginx}"

if [[ -n "${target_host}" ]] && docker inspect "${target_container}" >/dev/null 2>&1; then
    target_network="$(
        docker inspect -f '{{range $name, $network := .NetworkSettings.Networks}}{{println $name}}{{end}}' "${target_container}" \
            | head -n 1
    )"
    target_ip="$(
        docker inspect -f '{{range .NetworkSettings.Networks}}{{println .IPAddress}}{{end}}' "${target_container}" \
            | head -n 1
    )"

    if [[ -n "${target_network}" && -n "${target_ip}" ]]; then
        docker_network_args=(--network "${target_network}" --add-host "${target_host}:${target_ip}")
    fi
fi

docker run --rm \
    "${docker_network_args[@]}" \
    -v "$(cd "${OUTPUT_DIR}" && pwd):/zap/wrk" \
    ghcr.io/zaproxy/zaproxy:stable \
    zap-baseline.py \
    -t "${TARGET_URL}" \
    "${zap_policy_args[@]}" \
    -r report.html \
    -J report.json \
    -w report.md
