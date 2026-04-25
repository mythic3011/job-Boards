#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

if [[ "${BT_DRY_RUN}" == "1" ]]; then
    bt_log "DRY-RUN docker compose -f ${BT_COMPOSE_APP_FILE} up -d --build"
    exit 0
fi

bt_ensure_app_plane_network
bt_compose "${BT_COMPOSE_APP_FILE}" up -d --build
bt_compose "${BT_COMPOSE_APP_FILE}" restart laravel.test
bt_compose "${BT_COMPOSE_APP_FILE}" up -d --no-deps --force-recreate nginx
