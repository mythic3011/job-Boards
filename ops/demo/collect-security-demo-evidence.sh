#!/usr/bin/env bash

set -euo pipefail

usage() {
    cat <<'EOF'
Usage: ops/demo/collect-security-demo-evidence.sh <label> <target-url> [output-root]

Example:
  ops/demo/collect-security-demo-evidence.sh deployed https://jb.mythic3011.com demo-artifacts/security-demo
EOF
}

LABEL="${1:-}"
TARGET_URL="${2:-}"
OUTPUT_ROOT="${3:-demo-artifacts/security-demo}"

[[ -n "${LABEL}" && -n "${TARGET_URL}" ]] || {
    usage
    exit 1
}

command -v curl >/dev/null 2>&1 || {
    printf 'ERROR: curl is required.\n' >&2
    exit 1
}

command -v openssl >/dev/null 2>&1 || {
    printf 'ERROR: openssl is required.\n' >&2
    exit 1
}

OUTPUT_DIR="${OUTPUT_ROOT}/${LABEL}"
mkdir -p "${OUTPUT_DIR}"

TARGET_HOST="$(python3 - "${TARGET_URL}" <<'PY'
from urllib.parse import urlparse
import sys

parsed = urlparse(sys.argv[1])
if not parsed.scheme or not parsed.hostname:
    raise SystemExit(1)
print(parsed.hostname)
PY
)"

CHECKLIST_FILE="${OUTPUT_DIR}/checklist.md"
HEADERS_FILE="${OUTPUT_DIR}/curl-headers.txt"
CERT_FILE="${OUTPUT_DIR}/openssl-certificate.txt"
MANIFEST_FILE="${OUTPUT_DIR}/manifest.json"

curl -kfsSIL "${TARGET_URL}" > "${HEADERS_FILE}"
printf 'Q\n' | openssl s_client -connect "${TARGET_HOST}:443" -servername "${TARGET_HOST}" > "${CERT_FILE}" 2>&1

cat > "${CHECKLIST_FILE}" <<EOF
# Security Demo Checklist

- Label: ${LABEL}
- Target URL: ${TARGET_URL}
- Why No Padlock: https://www.whynopadlock.com/index.html
- SSL Labs: https://www.ssllabs.com/ssltest/analyze.html?d=${TARGET_HOST}
- Local headers artifact: ${HEADERS_FILE##${OUTPUT_DIR}/}
- Local certificate artifact: ${CERT_FILE##${OUTPUT_DIR}/}

Manual screenshot checklist:

1. Submit ${TARGET_URL} to Why No Padlock and save the screenshot beside this checklist.
2. Submit ${TARGET_URL} to SSL Labs and save the final grade screenshot beside this checklist.
3. Pair this folder with the matching ZAP before/after report folder.
EOF

python3 - "${MANIFEST_FILE}" "${LABEL}" "${TARGET_URL}" "${TARGET_HOST}" <<'PY'
import json
import sys
from datetime import datetime, timezone

path, label, target_url, target_host = sys.argv[1:]
payload = {
    "label": label,
    "target_url": target_url,
    "target_host": target_host,
    "generated_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "artifacts": {
        "headers": "curl-headers.txt",
        "certificate": "openssl-certificate.txt",
        "checklist": "checklist.md",
    },
    "external_checks": {
        "whynopadlock": "https://www.whynopadlock.com/index.html",
        "ssllabs": f"https://www.ssllabs.com/ssltest/analyze.html?d={target_host}",
    },
}

with open(path, "w", encoding="utf-8") as handle:
    json.dump(payload, handle, indent=2)
    handle.write("\n")
PY
