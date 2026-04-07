#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

"${SCRIPT_DIR}/honeypot-403.sh"
"${SCRIPT_DIR}/crowdsec-degraded.sh"
"${SCRIPT_DIR}/obs-isolation.sh"
