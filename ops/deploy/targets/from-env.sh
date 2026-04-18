#!/usr/bin/env bash

TARGETS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./_builder.sh
source "${TARGETS_DIR}/_builder.sh"

: "${TARGET_DOMAIN:?Set TARGET_DOMAIN for the reverse-proxy deploy target}"
: "${TARGET_HOST:?Set TARGET_HOST for the reverse-proxy deploy target}"
: "${TARGET_REMOTE_ROOT:?Set TARGET_REMOTE_ROOT for the reverse-proxy deploy target}"
: "${TARGET_COMPOSE_PROJECT_NAME:?Set TARGET_COMPOSE_PROJECT_NAME for the reverse-proxy deploy target}"

build_reverse_proxy_target
