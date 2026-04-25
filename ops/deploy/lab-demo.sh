#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

LAB_DEPLOY_HOST="${LAB_DEPLOY_HOST:-${1:-}}"
DEPLOY_REF="${2:-HEAD}"

if [[ -z "${LAB_DEPLOY_HOST}" ]]; then
    printf 'Usage: ops/deploy/lab-demo.sh <lab-host> [git-ref]\n' >&2
    exit 1
fi

export DEPLOY_WORKTREE_SNAPSHOT="${DEPLOY_WORKTREE_SNAPSHOT:-true}"
export LAB_DEPLOY_HOST
export LAB_DEPLOY_PUBLIC_HOST="${LAB_DEPLOY_PUBLIC_HOST:-}"
export LAB_DEPLOY_EXTRA_HOSTS="${LAB_DEPLOY_EXTRA_HOSTS:-}"
export LAB_DEPLOY_DOMAIN="${LAB_DEPLOY_DOMAIN:-${LAB_DEPLOY_PUBLIC_HOST:-${LAB_DEPLOY_HOST}}}"
export LAB_DEPLOY_APP_URL="${LAB_DEPLOY_APP_URL:-https://${LAB_DEPLOY_DOMAIN}}"
export LAB_DEPLOY_ASSET_URL="${LAB_DEPLOY_ASSET_URL:-}"
export LAB_DEPLOY_SSH_USER="${LAB_DEPLOY_SSH_USER:-user}"
export LAB_DEPLOY_REMOTE_ROOT="${LAB_DEPLOY_REMOTE_ROOT:-/opt/jobs-borads-demo}"
export LAB_DEPLOY_COMPOSE_PROJECT_NAME="${LAB_DEPLOY_COMPOSE_PROJECT_NAME:-jobs-borads-demo}"
export LAB_DEPLOY_BT_STATE_DIR="${LAB_DEPLOY_BT_STATE_DIR:-/opt/jobs-borads-demo/state}"
export LAB_LAN_ADDRESS="${LAB_LAN_ADDRESS:-192.168.153.100/24}"

exec "${ROOT_DIR}/ops/deploy/vps-deploy.sh" lab-env "${DEPLOY_REF}"
