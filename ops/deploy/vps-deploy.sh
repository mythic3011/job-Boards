#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

usage() {
    cat <<'EOF'
Usage: ops/deploy/vps-deploy.sh <target> [git-ref]

Example:
  ops/deploy/vps-deploy.sh jb.mythic3011.com main

Reverse-proxy target TLS inputs:
  TARGET_TLS_MODE=cloudflare-origin|letsencrypt|custom
  TARGET_NGINX_CERT_PATH=/path/to/cert.pem
  TARGET_NGINX_KEY_PATH=/path/to/key.pem

Optional local env for first install:
  JB_INSTALL_ADMIN_EMAIL
  JB_INSTALL_ADMIN_PASSWORD
  JB_INSTALL_ADMIN_NAME
  JB_INSTALL_TOTP_SECRET
  JB_INSTALL_APP_NAME
  JB_INSTALL_TIMEZONE
  JB_INSTALL_DEMO_DATA=true|false

Lab-network options live in the target profile:
  LAB_CONFIGURE_NETPLAN=true
  LAB_WAN_MODE=dhcp|static
  LAB_WAN_IFACE=eth0
  LAB_WAN_ADDRESS=158.132.209.50/24
  LAB_WAN_GATEWAY=158.132.209.28
  LAB_WAN_DNS=1.1.1.1,8.8.8.8
  LAB_LAN_IFACE=eth1
  LAB_LAN_ADDRESS=192.168.153.2/24
  LAB_NETPLAN_APPLY=true
EOF
}

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || die "Missing required command: $1"
}

render_template() {
    local template="$1"
    local output="$2"
    python3 - "$template" "$output" <<'PY'
import os
import sys

template_path, output_path = sys.argv[1:]
with open(template_path, "r", encoding="utf-8") as handle:
    content = handle.read()

replacements = {
    "__DEPLOY_DOMAIN__": os.environ["DEPLOY_DOMAIN"],
    "__DEPLOY_NGINX_CERT_PATH__": os.environ["DEPLOY_NGINX_CERT_PATH"],
    "__DEPLOY_NGINX_KEY_PATH__": os.environ["DEPLOY_NGINX_KEY_PATH"],
    "__DEPLOY_NGINX_PROXY_PASS__": os.environ["DEPLOY_NGINX_PROXY_PASS"],
}

for needle, value in replacements.items():
    content = content.replace(needle, value)

with open(output_path, "w", encoding="utf-8") as handle:
    handle.write(content)
PY
}

TARGET_NAME="${1:-}"
REF="${2:-HEAD}"
[[ -n "${TARGET_NAME}" ]] || { usage; exit 1; }

TARGET_FILE="${SCRIPT_DIR}/targets/${TARGET_NAME}.sh"
[[ -f "${TARGET_FILE}" ]] || die "Unknown deploy target: ${TARGET_NAME}"

require_cmd git
require_cmd ssh
require_cmd scp
require_cmd python3

cd "${REPO_ROOT}"

if [[ -n "$(git status --porcelain)" ]]; then
    die "Working tree is dirty. Commit or stash changes before deploy."
fi

RESOLVED_REF="$(git rev-parse --verify "${REF}^{commit}")"
source "${TARGET_FILE}"
export DEPLOY_DOMAIN DEPLOY_NGINX_CERT_PATH DEPLOY_NGINX_KEY_PATH DEPLOY_NGINX_PROXY_PASS

REMOTE="${DEPLOY_SSH_USER}@${DEPLOY_HOST}"
SSH_TARGET=(-p "${DEPLOY_SSH_PORT}")
SCP_TARGET=(-P "${DEPLOY_SSH_PORT}")
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

ARCHIVE_PATH="${TMP_DIR}/release.tar.gz"
REMOTE_SCRIPT="${TMP_DIR}/deploy.remote.sh"
REMOTE_ENV="${TMP_DIR}/deploy.remote.env"
REMOTE_SITE="${TMP_DIR}/${DEPLOY_NGINX_SITE_NAME:-deploy-site.conf}"
TEMPLATE_PATH="${SCRIPT_DIR}/templates/nginx-site.conf.tpl"

git archive --format=tar.gz --output="${ARCHIVE_PATH}" "${RESOLVED_REF}"
BOOTSTRAP_MODE="production"
if ssh "${SSH_TARGET[@]}" "${REMOTE}" "test -f '${DEPLOY_REMOTE_ROOT}/shared/.env'" >/dev/null 2>&1; then
    BOOTSTRAP_MODE="dev"
fi

cat > "${REMOTE_ENV}" <<EOF
DEPLOY_DOMAIN=${DEPLOY_DOMAIN}
DEPLOY_REMOTE_ROOT=${DEPLOY_REMOTE_ROOT}
DEPLOY_APP_PORT=${DEPLOY_APP_PORT}
DEPLOY_APP_SSL_PORT=${DEPLOY_APP_SSL_PORT}
DEPLOY_APP_URL=${DEPLOY_APP_URL}
DEPLOY_ASSET_URL=${DEPLOY_ASSET_URL}
DEPLOY_COMPOSE_PROJECT_NAME=${DEPLOY_COMPOSE_PROJECT_NAME}
DEPLOY_BT_STATE_DIR=${DEPLOY_BT_STATE_DIR}
DEPLOY_DB_DATABASE=${DEPLOY_DB_DATABASE}
DEPLOY_DB_USERNAME=${DEPLOY_DB_USERNAME}
DEPLOY_MONITORING_ADMIN_USERNAME=${DEPLOY_MONITORING_ADMIN_USERNAME}
DEPLOY_TIMEZONE=${DEPLOY_TIMEZONE}
DEPLOY_NGINX_SITE_NAME=${DEPLOY_NGINX_SITE_NAME}
DEPLOY_NGINX_CERT_PATH=${DEPLOY_NGINX_CERT_PATH}
DEPLOY_NGINX_KEY_PATH=${DEPLOY_NGINX_KEY_PATH}
DEPLOY_NGINX_PROXY_PASS=${DEPLOY_NGINX_PROXY_PASS}
DEPLOY_BOOTSTRAP_MODE=${BOOTSTRAP_MODE}
DEPLOY_INSTALL_HOST_NGINX=${DEPLOY_INSTALL_HOST_NGINX:-true}
EOF

if [[ -n "${JB_INSTALL_ADMIN_EMAIL:-}" ]]; then
    cat >> "${REMOTE_ENV}" <<EOF
JB_INSTALL_ADMIN_EMAIL=${JB_INSTALL_ADMIN_EMAIL}
JB_INSTALL_ADMIN_PASSWORD=${JB_INSTALL_ADMIN_PASSWORD:-}
JB_INSTALL_ADMIN_NAME=${JB_INSTALL_ADMIN_NAME:-Admin User}
JB_INSTALL_TOTP_SECRET=${JB_INSTALL_TOTP_SECRET:-}
JB_INSTALL_APP_NAME=${JB_INSTALL_APP_NAME:-Jobs Boards}
JB_INSTALL_TIMEZONE=${JB_INSTALL_TIMEZONE:-${DEPLOY_TIMEZONE}}
JB_INSTALL_DEMO_DATA=${JB_INSTALL_DEMO_DATA:-false}
EOF
fi

for optional_key in \
    LAB_CONFIGURE_NETPLAN \
    LAB_NETPLAN_APPLY \
    LAB_WAN_IFACE \
    LAB_WAN_MODE \
    LAB_WAN_ADDRESS \
    LAB_WAN_GATEWAY \
    LAB_WAN_DNS \
    LAB_LAN_IFACE \
    LAB_LAN_ADDRESS; do
    if [[ -n "${!optional_key:-}" ]]; then
        printf '%s=%s\n' "${optional_key}" "${!optional_key}" >> "${REMOTE_ENV}"
    fi
done

if [[ "${DEPLOY_INSTALL_HOST_NGINX:-true}" == "true" ]]; then
    render_template "${TEMPLATE_PATH}" "${REMOTE_SITE}"
else
    : > "${REMOTE_SITE}"
fi

cat > "${REMOTE_SCRIPT}" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "$0")/deploy.remote.env"

release_sha="$1"
release_archive="$2"
site_file="$3"

remote_current="${DEPLOY_REMOTE_ROOT}/current"
remote_shared="${DEPLOY_REMOTE_ROOT}/shared"
remote_release="${DEPLOY_REMOTE_ROOT}/releases/${release_sha}"
runtime_dir="${DEPLOY_BT_STATE_DIR}/runtime"
netplan_path="/etc/netplan/60-jobs-boards-lab.yaml"

configure_lab_netplan() {
    if [[ "${LAB_CONFIGURE_NETPLAN:-false}" != "true" ]]; then
        return 0
    fi

    LAB_WAN_IFACE="${LAB_WAN_IFACE:-eth0}" \
    LAB_WAN_MODE="${LAB_WAN_MODE:-dhcp}" \
    LAB_WAN_ADDRESS="${LAB_WAN_ADDRESS:-}" \
    LAB_WAN_GATEWAY="${LAB_WAN_GATEWAY:-}" \
    LAB_WAN_DNS="${LAB_WAN_DNS:-1.1.1.1,8.8.8.8}" \
    LAB_LAN_IFACE="${LAB_LAN_IFACE:-eth1}" \
    LAB_LAN_ADDRESS="${LAB_LAN_ADDRESS:-192.168.153.2/24}" \
    NETPLAN_PATH="${netplan_path}" \
    python3 - <<'PY'
import os

wan_iface = os.environ["LAB_WAN_IFACE"]
wan_mode = os.environ["LAB_WAN_MODE"].strip().lower()
wan_address = os.environ["LAB_WAN_ADDRESS"].strip()
wan_gateway = os.environ["LAB_WAN_GATEWAY"].strip()
wan_dns = [item.strip() for item in os.environ["LAB_WAN_DNS"].split(",") if item.strip()]
lan_iface = os.environ["LAB_LAN_IFACE"].strip()
lan_address = os.environ["LAB_LAN_ADDRESS"].strip()
path = os.environ["NETPLAN_PATH"]

if wan_mode not in {"dhcp", "static"}:
    raise SystemExit("LAB_WAN_MODE must be dhcp or static")

lines = [
    "network:",
    "  version: 2",
    "  renderer: networkd",
    "  ethernets:",
    f"    {wan_iface}:",
]

if wan_mode == "dhcp":
    lines.append("      dhcp4: true")
else:
    if not wan_address or not wan_gateway:
        raise SystemExit("LAB_WAN_ADDRESS and LAB_WAN_GATEWAY are required for static WAN mode")
    lines.extend([
        "      dhcp4: false",
        "      addresses:",
        f"        - {wan_address}",
        "      routes:",
        "        - to: default",
        f"          via: {wan_gateway}",
    ])

if wan_dns:
    lines.extend([
        "      nameservers:",
        "        addresses:",
        *[f"          - {item}" for item in wan_dns],
    ])

if lan_iface and lan_address:
    lines.extend([
        f"    {lan_iface}:",
        "      dhcp4: false",
        "      addresses:",
        f"        - {lan_address}",
    ])

with open(path, "w", encoding="utf-8") as handle:
    handle.write("\n".join(lines) + "\n")
PY

    netplan generate
    if [[ "${LAB_NETPLAN_APPLY:-false}" == "true" ]]; then
        netplan apply
    fi
}

mkdir -p "${DEPLOY_REMOTE_ROOT}/releases" "${remote_shared}" "${DEPLOY_BT_STATE_DIR}" "${runtime_dir}"
rm -rf "${remote_release}"
mkdir -p "${remote_release}"
tar -xzf "${release_archive}" -C "${remote_release}"

if [[ ! -f "${remote_shared}/.env" ]]; then
    cp "${remote_release}/.env.example" "${remote_shared}/.env"
fi

ln -sfn "${remote_shared}/.env" "${remote_release}/.env"
ln -sfn "${remote_release}" "${remote_current}"
configure_lab_netplan

DEPLOY_APP_URL="${DEPLOY_APP_URL}" \
DEPLOY_ASSET_URL="${DEPLOY_ASSET_URL}" \
DEPLOY_APP_PORT="${DEPLOY_APP_PORT}" \
DEPLOY_APP_SSL_PORT="${DEPLOY_APP_SSL_PORT}" \
DEPLOY_DB_DATABASE="${DEPLOY_DB_DATABASE}" \
DEPLOY_DB_USERNAME="${DEPLOY_DB_USERNAME}" \
DEPLOY_MONITORING_ADMIN_USERNAME="${DEPLOY_MONITORING_ADMIN_USERNAME}" \
python3 - "${remote_shared}/.env" <<'PY'
import os
import re
import sys

path = sys.argv[1]
updates = {
    "APP_ENV": "production",
    "APP_DEBUG": "false",
    "APP_URL": os.environ["DEPLOY_APP_URL"],
    "ASSET_URL": os.environ["DEPLOY_ASSET_URL"],
    "APP_PORT": os.environ["DEPLOY_APP_PORT"],
    "APP_SSL_PORT": os.environ["DEPLOY_APP_SSL_PORT"],
    "DB_DATABASE": os.environ["DEPLOY_DB_DATABASE"],
    "DB_USERNAME": os.environ["DEPLOY_DB_USERNAME"],
    "MONITORING_ADMIN_USERNAME": os.environ["DEPLOY_MONITORING_ADMIN_USERNAME"],
}
with open(path, "r", encoding="utf-8") as handle:
    content = handle.read()
for key, value in updates.items():
    pattern = r"^" + re.escape(key) + r"=.*$"
    line = f"{key}={value}"
    if re.search(pattern, content, flags=re.MULTILINE):
        content = re.sub(pattern, line, content, flags=re.MULTILINE)
    else:
        content = content.rstrip("\n") + "\n" + line + "\n"
with open(path, "w", encoding="utf-8") as handle:
    handle.write(content)
PY

cd "${remote_current}"
if [[ "${DEPLOY_BOOTSTRAP_MODE}" == "production" ]]; then
    ./bootstrap-env.sh production
else
    ./bootstrap-env.sh dev
fi

export BT_STATE_DIR="${DEPLOY_BT_STATE_DIR}"
export BT_RUNTIME_DIR="${runtime_dir}"
export BT_HONEYPOT_SOURCE="${remote_current}/docker/nginx/includes/blue-team-honeypot.conf"
export COMPOSE_PROJECT_NAME="${DEPLOY_COMPOSE_PROJECT_NAME}"

./setup-blue-team-vm.sh app
./setup-blue-team-vm.sh obs

if [[ -n "${JB_INSTALL_ADMIN_EMAIL:-}" ]]; then
    install_args=(
        php artisan install:headless
        --admin-email="${JB_INSTALL_ADMIN_EMAIL}"
        --admin-password="${JB_INSTALL_ADMIN_PASSWORD}"
        --admin-name="${JB_INSTALL_ADMIN_NAME:-Admin User}"
        --app-name="${JB_INSTALL_APP_NAME:-Jobs Boards}"
        --app-url="${DEPLOY_APP_URL}"
        --timezone="${JB_INSTALL_TIMEZONE:-${DEPLOY_TIMEZONE}}"
    )
    if [[ -n "${JB_INSTALL_TOTP_SECRET:-}" ]]; then
        install_args+=(--two-factor-secret="${JB_INSTALL_TOTP_SECRET}")
    fi
    if [[ "${JB_INSTALL_DEMO_DATA:-false}" == "true" ]]; then
        install_args+=(--install-demo-data)
    fi
    docker compose -f compose.app.yml exec -T laravel.test "${install_args[@]}"
fi

if [[ "${DEPLOY_INSTALL_HOST_NGINX:-true}" == "true" ]]; then
    [[ -f "${DEPLOY_NGINX_CERT_PATH}" ]] || {
        printf 'Missing TLS certificate file: %s\n' "${DEPLOY_NGINX_CERT_PATH}" >&2
        exit 1
    }
    [[ -f "${DEPLOY_NGINX_KEY_PATH}" ]] || {
        printf 'Missing TLS private key file: %s\n' "${DEPLOY_NGINX_KEY_PATH}" >&2
        exit 1
    }
    install -m 0644 "${site_file}" "/etc/nginx/sites-available/${DEPLOY_NGINX_SITE_NAME}"
    ln -sfn "/etc/nginx/sites-available/${DEPLOY_NGINX_SITE_NAME}" "/etc/nginx/sites-enabled/${DEPLOY_NGINX_SITE_NAME}"
    nginx -t
    systemctl reload nginx
    curl -kfsS --resolve "${DEPLOY_DOMAIN}:443:127.0.0.1" "https://${DEPLOY_DOMAIN}/up" >/dev/null
else
    curl -kfsS "https://127.0.0.1/up" >/dev/null
fi
EOF

chmod 0755 "${REMOTE_SCRIPT}"

scp_args=("${SCP_TARGET[@]}" "${ARCHIVE_PATH}" "${REMOTE_SCRIPT}" "${REMOTE_ENV}")
if [[ "${DEPLOY_INSTALL_HOST_NGINX:-true}" == "true" ]]; then
    scp_args+=("${REMOTE_SITE}")
fi
scp_args+=("${REMOTE}:${DEPLOY_REMOTE_ROOT}/")
scp "${scp_args[@]}"
ssh "${SSH_TARGET[@]}" "${REMOTE}" "DEPLOY_BOOTSTRAP_MODE='${BOOTSTRAP_MODE}' bash '${DEPLOY_REMOTE_ROOT}/deploy.remote.sh' '${RESOLVED_REF}' '${DEPLOY_REMOTE_ROOT}/$(basename "${ARCHIVE_PATH}")' '${DEPLOY_REMOTE_ROOT}/$(basename "${REMOTE_SITE}")'"
