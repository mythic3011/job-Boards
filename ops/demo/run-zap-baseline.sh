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

docker run --rm \
    -v "$(cd "${OUTPUT_DIR}" && pwd):/zap/wrk" \
    ghcr.io/zaproxy/zaproxy:stable \
    zap-baseline.py \
    -t "${TARGET_URL}" \
    -r report.html \
    -J report.json \
    -w report.md
