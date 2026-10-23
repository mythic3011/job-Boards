#!/usr/bin/env bash
# Generate a 256-bit Laravel key ONCE. Existing installations must supply their
# original APP_KEY as JOB_BOARDS_APP_KEY before switching deployments.
set -euo pipefail
# shellcheck source=/dev/null
. "$ROOT/scripts/lib/common.sh"

key="$(env_get JOB_BOARDS_APP_KEY)"
if [ -z "$key" ]; then
    key="base64:$(openssl rand -base64 32 | tr -d '\n')"
    env_set JOB_BOARDS_APP_KEY "$key"
    audit secret.generated key=JOB_BOARDS_APP_KEY
    say "Generated persistent JOB_BOARDS_APP_KEY (not printed)"
fi

case "$key" in
    base64:*)
        encoded="${key#base64:}"
        # Base64 of exactly 32 bytes: 44 chars ending with one '='.
        if ! printf '%s' "$encoded" | grep -Eq '^[A-Za-z0-9+/]{43}=$'; then
            die "JOB_BOARDS_APP_KEY must be base64 of exactly 32 random bytes"
        fi
        ;;
    *)
        # Laravel also accepts a raw 32-byte key for AES-256-CBC.
        [ "${#key}" -eq 32 ] || die "JOB_BOARDS_APP_KEY must be a 32-byte raw key or base64: key"
        ;;
esac
