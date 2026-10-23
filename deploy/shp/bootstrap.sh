#!/usr/bin/env bash
# Backwards-compatible launcher. Prefer ./deploy.sh up.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
exec "$ROOT/deploy.sh" up "$@"
