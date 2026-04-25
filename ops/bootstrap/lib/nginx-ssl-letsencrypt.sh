#!/usr/bin/env bash

set -euo pipefail

BT_NGINX_SSL_LE_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BT_NGINX_SSL_LE_OPS_DIR="$(cd "${BT_NGINX_SSL_LE_LIB_DIR}/../.." && pwd)"
BT_NGINX_SSL_LE_ROOT_DIR="$(cd "${BT_NGINX_SSL_LE_LIB_DIR}/../../.." && pwd)"
# shellcheck source=../../lib/common.sh
source "${BT_NGINX_SSL_LE_OPS_DIR}/lib/common.sh"

: "${SSL_ACME_CLIENT:=acme.sh}"
: "${SSL_ACME_EMAIL:=${BT_CERTBOT_EMAIL:-}}"
: "${SSL_CERT_DOMAIN:=${TARGET_NGINX_CERT_DOMAIN:-${TARGET_DOMAIN:-}}}"
: "${SSL_CERT_ALT_NAMES:=}"
: "${BT_NGINX_SSL_ACME_SH_BIN:=}"
: "${BT_NGINX_SSL_CERTBOT_BIN:=}"
: "${BT_NGINX_SSL_ACME_CA:=letsencrypt}"
: "${BT_NGINX_SSL_CERTBOT_SERVER:=https://acme-v02.api.letsencrypt.org/directory}"
: "${BT_NGINX_SSL_ACME_HOME:=${BT_STATE_DIR}/acme.sh}"
: "${BT_NGINX_SSL_CERTBOT_STATE_DIR:=${BT_STATE_DIR}/certbot}"
: "${BT_NGINX_SSL_CERTBOT_CONFIG_DIR:=${BT_NGINX_SSL_CERTBOT_STATE_DIR}/config}"
: "${BT_NGINX_SSL_CERTBOT_WORK_DIR:=${BT_NGINX_SSL_CERTBOT_STATE_DIR}/work}"
: "${BT_NGINX_SSL_CERTBOT_LOG_DIR:=${BT_NGINX_SSL_CERTBOT_STATE_DIR}/log}"
: "${BT_NGINX_SSL_CERTBOT_HOOK_DIR:=${BT_NGINX_SSL_CERTBOT_CONFIG_DIR}/hooks}"
: "${BT_NGINX_SSL_CERTBOT_ENV_FILE:=${BT_NGINX_SSL_CERTBOT_CONFIG_DIR}/cloudflare.env}"
: "${BT_NGINX_SSL_RUNTIME_DIR:=${BT_RUNTIME_DIR}/nginx-ssl}"
: "${BT_NGINX_SSL_CERT_PATH:=${BT_NGINX_SSL_RUNTIME_DIR}/fullchain.pem}"
: "${BT_NGINX_SSL_KEY_PATH:=${BT_NGINX_SSL_RUNTIME_DIR}/privkey.pem}"
: "${BT_NGINX_SSL_RENEW_CRON_FILE:=/etc/cron.d/nginx-ssl-letsencrypt}"
: "${BT_NGINX_SSL_RENEW_CRON_SCHEDULE:=17 3 * * *}"
: "${BT_NGINX_SSL_DNS_SLEEP:=120}"
: "${BT_NGINX_SSL_DNS_PROPAGATION_SECONDS:=30}"
: "${BT_NGINX_SSL_KEY_TYPE:=ec-256}"
: "${BT_NGINX_SSL_NGINX_RELOAD_COMMAND:=}"
: "${BT_NGINX_SSL_NGINX_SERVICE:=nginx}"
: "${BT_NGINX_SSL_COMPOSE_FILE:=${BT_COMPOSE_APP_FILE}}"
: "${BT_NGINX_SSL_COMPOSE_SERVICE:=nginx}"

bt_nginx_ssl_letsencrypt_domain() {
    [[ -n "${SSL_CERT_DOMAIN:-}" ]] || bt_die "SSL_CERT_DOMAIN is required for letsencrypt DNS-01 provisioning."
    printf '%s\n' "${SSL_CERT_DOMAIN}"
}

bt_nginx_ssl_letsencrypt_collect_domains() {
    local primary raw candidate existing already_present
    local emitted_domains=()
    local extra_domains=()

    primary="$(bt_nginx_ssl_letsencrypt_domain)"
    emitted_domains=("${primary}")
    printf '%s\n' "${primary}"

    raw="${SSL_CERT_ALT_NAMES:-}"
    [[ -n "${raw}" ]] || return 0

    read -r -a extra_domains <<< "${raw//,/ }"
    for candidate in "${extra_domains[@]}"; do
        [[ -n "${candidate}" ]] || continue
        already_present=0
        for existing in "${emitted_domains[@]}"; do
            if [[ "${existing}" == "${candidate}" ]]; then
                already_present=1
                break
            fi
        done
        [[ "${already_present}" == "1" ]] && continue
        emitted_domains+=("${candidate}")
        printf '%s\n' "${candidate}"
    done
}

bt_nginx_ssl_letsencrypt_validate_domains() {
    local domains=()
    local domain

    while IFS= read -r domain; do
        [[ -n "${domain}" ]] || continue
        domains+=("${domain}")
    done < <(bt_nginx_ssl_letsencrypt_collect_domains)
    for domain in "${domains[@]}"; do
        [[ "${domain}" =~ ^(\*\.)?[A-Za-z0-9][A-Za-z0-9.-]*[A-Za-z0-9]$ ]] || bt_die "Invalid certificate domain: ${domain}"
    done
}

bt_nginx_ssl_letsencrypt_primary_certbot_domain() {
    local domain
    domain="$(bt_nginx_ssl_letsencrypt_domain)"
    if [[ "${domain}" == \*.* ]]; then
        printf '%s\n' "${domain#*.}"
        return 0
    fi

    printf '%s\n' "${domain}"
}

bt_nginx_ssl_letsencrypt_certbot_auth_hook_path() {
    printf '%s\n' "${BT_NGINX_SSL_CERTBOT_HOOK_DIR}/cloudflare-auth.sh"
}

bt_nginx_ssl_letsencrypt_certbot_cleanup_hook_path() {
    printf '%s\n' "${BT_NGINX_SSL_CERTBOT_HOOK_DIR}/cloudflare-cleanup.sh"
}

bt_nginx_ssl_letsencrypt_certbot_deploy_hook_path() {
    printf '%s\n' "${BT_NGINX_SSL_CERTBOT_HOOK_DIR}/install-and-reload.sh"
}

bt_nginx_ssl_letsencrypt_resolve_acme_sh_bin() {
    local candidate

    for candidate in \
        "${BT_NGINX_SSL_ACME_SH_BIN:-}" \
        "acme.sh" \
        "${HOME:-}/.acme.sh/acme.sh"
    do
        [[ -n "${candidate}" ]] || continue
        if [[ "${candidate}" == */* ]]; then
            [[ -x "${candidate}" ]] && {
                printf '%s\n' "${candidate}"
                return 0
            }
            continue
        fi

        if command -v "${candidate}" >/dev/null 2>&1; then
            command -v "${candidate}"
            return 0
        fi
    done

    return 1
}

bt_nginx_ssl_letsencrypt_resolve_certbot_bin() {
    local candidate

    for candidate in \
        "${BT_NGINX_SSL_CERTBOT_BIN:-}" \
        "certbot"
    do
        [[ -n "${candidate}" ]] || continue
        if [[ "${candidate}" == */* ]]; then
            [[ -x "${candidate}" ]] && {
                printf '%s\n' "${candidate}"
                return 0
            }
            continue
        fi

        if command -v "${candidate}" >/dev/null 2>&1; then
            command -v "${candidate}"
            return 0
        fi
    done

    return 1
}

bt_nginx_ssl_letsencrypt_client_available() {
    local client="$1"

    case "${client}" in
        acme.sh)
            bt_nginx_ssl_letsencrypt_resolve_acme_sh_bin >/dev/null
            ;;
        certbot)
            bt_nginx_ssl_letsencrypt_resolve_certbot_bin >/dev/null
            ;;
        *)
            return 1
            ;;
    esac
}

bt_nginx_ssl_letsencrypt_resolve_client() {
    case "${SSL_ACME_CLIENT:-acme.sh}" in
        ""|auto|acme.sh)
            if bt_nginx_ssl_letsencrypt_client_available "acme.sh"; then
                printf '%s\n' "acme.sh"
                return 0
            fi
            if bt_nginx_ssl_letsencrypt_client_available "certbot"; then
                bt_warn "acme.sh is unavailable; falling back to certbot for DNS-01 issuance."
                printf '%s\n' "certbot"
                return 0
            fi
            bt_die "Neither acme.sh nor certbot is available for letsencrypt DNS-01 provisioning."
            ;;
        certbot)
            bt_nginx_ssl_letsencrypt_client_available "certbot" || bt_die "certbot is required when SSL_ACME_CLIENT=certbot."
            printf '%s\n' "certbot"
            ;;
        *)
            bt_die "Unsupported SSL_ACME_CLIENT: ${SSL_ACME_CLIENT}. Expected acme.sh, certbot, or auto."
            ;;
    esac
}

bt_nginx_ssl_letsencrypt_cert_materialized() {
    [[ -s "${BT_NGINX_SSL_CERT_PATH}" ]] || return 1
    [[ -s "${BT_NGINX_SSL_KEY_PATH}" ]] || return 1
    command -v openssl >/dev/null 2>&1 || return 0
    openssl x509 -noout -in "${BT_NGINX_SSL_CERT_PATH}" >/dev/null 2>&1
}

bt_nginx_ssl_letsencrypt_render_reload_command() {
    local compose_file_quoted compose_service_quoted service_quoted

    if [[ -n "${BT_NGINX_SSL_NGINX_RELOAD_COMMAND:-}" ]]; then
        printf '%s\n' "${BT_NGINX_SSL_NGINX_RELOAD_COMMAND}"
        return 0
    fi

    if command -v systemctl >/dev/null 2>&1 && systemctl list-unit-files "${BT_NGINX_SSL_NGINX_SERVICE}.service" >/dev/null 2>&1; then
        printf -v service_quoted '%q' "${BT_NGINX_SSL_NGINX_SERVICE}"
        printf 'systemctl reload %s\n' "${service_quoted}"
        return 0
    fi

    if command -v docker >/dev/null 2>&1 && [[ -f "${BT_NGINX_SSL_COMPOSE_FILE}" ]]; then
        printf -v compose_file_quoted '%q' "${BT_NGINX_SSL_COMPOSE_FILE}"
        printf -v compose_service_quoted '%q' "${BT_NGINX_SSL_COMPOSE_SERVICE}"
        printf 'if docker compose -f %s exec -T %s nginx -t >/dev/null 2>&1; then docker compose -f %s exec -T %s nginx -s reload; fi\n' \
            "${compose_file_quoted}" \
            "${compose_service_quoted}" \
            "${compose_file_quoted}" \
            "${compose_service_quoted}"
        return 0
    fi

    printf '%s\n' "true"
}

bt_nginx_ssl_letsencrypt_reload_nginx_gracefully() {
    local reload_command

    reload_command="$(bt_nginx_ssl_letsencrypt_render_reload_command)"
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN ${reload_command}"
        return 0
    fi

    /bin/bash -lc "${reload_command}"
}

bt_nginx_ssl_letsencrypt_render_env_assignment() {
    local key="$1"
    local value="$2"
    local quoted_value

    printf -v quoted_value '%q' "${value}"
    printf '%s=%s\n' "${key}" "${quoted_value}"
}

bt_nginx_ssl_letsencrypt_render_env_file() {
    bt_nginx_ssl_letsencrypt_render_env_assignment "CF_Token" "${CF_Token:-}"
    bt_nginx_ssl_letsencrypt_render_env_assignment "CF_Zone_ID" "${CF_Zone_ID:-}"
    bt_nginx_ssl_letsencrypt_render_env_assignment "BT_NGINX_SSL_CERT_PATH" "${BT_NGINX_SSL_CERT_PATH}"
    bt_nginx_ssl_letsencrypt_render_env_assignment "BT_NGINX_SSL_KEY_PATH" "${BT_NGINX_SSL_KEY_PATH}"
    bt_nginx_ssl_letsencrypt_render_env_assignment "BT_NGINX_SSL_DNS_PROPAGATION_SECONDS" "${BT_NGINX_SSL_DNS_PROPAGATION_SECONDS}"
    bt_nginx_ssl_letsencrypt_render_env_assignment "BT_NGINX_SSL_NGINX_RELOAD_COMMAND" "$(bt_nginx_ssl_letsencrypt_render_reload_command)"
}

bt_nginx_ssl_letsencrypt_render_certbot_auth_hook() {
    local env_file
    env_file="$(bt_nginx_ssl_letsencrypt_certbot_env_file_path)"

    cat <<EOF
#!/usr/bin/env bash
set -euo pipefail

ENV_FILE=$(printf '%q' "${env_file}")
[[ -r "\${ENV_FILE}" ]] || {
    printf 'ERROR: missing certbot Cloudflare env file: %s\n' "\${ENV_FILE}" >&2
    exit 1
}

# shellcheck disable=SC1090
source "\${ENV_FILE}"

: "\${CF_Token:?CF_Token is required}"
: "\${CF_Zone_ID:?CF_Zone_ID is required}"
: "\${CERTBOT_DOMAIN:?CERTBOT_DOMAIN is required}"
: "\${CERTBOT_VALIDATION:?CERTBOT_VALIDATION is required}"

challenge_domain="\${CERTBOT_DOMAIN}"
if [[ "\${challenge_domain}" == \\*.* ]]; then
    challenge_domain="\${challenge_domain#*.}"
fi
record_name="_acme-challenge.\${challenge_domain}"

payload="\$(python3 - "\${record_name}" "\${CERTBOT_VALIDATION}" <<'PY'
import json
import sys

payload = {
    "type": "TXT",
    "name": sys.argv[1],
    "content": sys.argv[2],
    "ttl": 120,
}
print(json.dumps(payload, separators=(",", ":")))
PY
)"

response="\$(curl -fsS --retry 3 --retry-delay 2 \\
    -X POST "https://api.cloudflare.com/client/v4/zones/\${CF_Zone_ID}/dns_records" \\
    -H "Authorization: Bearer \${CF_Token}" \\
    -H "Content-Type: application/json" \\
    --data "\${payload}")"

record_id="\$(CF_CREATE_RESPONSE="\${response}" python3 - <<'PY'
import json
import os
import sys

response = json.loads(os.environ["CF_CREATE_RESPONSE"])
if not response.get("success"):
    errors = response.get("errors") or [{"message": "unknown Cloudflare API error"}]
    for error in errors:
        print(error.get("message", "unknown Cloudflare API error"), file=sys.stderr)
    sys.exit(1)

result = response.get("result") or {}
record_id = result.get("id")
if not record_id:
    print("Cloudflare API did not return a DNS record id.", file=sys.stderr)
    sys.exit(1)

sys.stdout.write(record_id)
PY
)"

sleep "\${BT_NGINX_SSL_DNS_PROPAGATION_SECONDS:-30}"
printf '%s\n' "\${record_id}"
EOF
}

bt_nginx_ssl_letsencrypt_render_certbot_cleanup_hook() {
    local env_file
    env_file="$(bt_nginx_ssl_letsencrypt_certbot_env_file_path)"

    cat <<EOF
#!/usr/bin/env bash
set -euo pipefail

ENV_FILE=$(printf '%q' "${env_file}")
[[ -r "\${ENV_FILE}" ]] || exit 0

# shellcheck disable=SC1090
source "\${ENV_FILE}"

record_id="\${CERTBOT_AUTH_OUTPUT:-}"
[[ -n "\${record_id}" ]] || exit 0

response="\$(curl -fsS --retry 3 --retry-delay 2 \\
    -X DELETE "https://api.cloudflare.com/client/v4/zones/\${CF_Zone_ID}/dns_records/\${record_id}" \\
    -H "Authorization: Bearer \${CF_Token}" \\
    -H "Content-Type: application/json")"

CF_DELETE_RESPONSE="\${response}" python3 - <<'PY'
import json
import os
import sys

response = json.loads(os.environ["CF_DELETE_RESPONSE"])
if response.get("success"):
    sys.exit(0)

errors = response.get("errors") or [{"message": "unknown Cloudflare API error"}]
for error in errors:
    print(error.get("message", "unknown Cloudflare API error"), file=sys.stderr)
sys.exit(1)
PY
EOF
}

bt_nginx_ssl_letsencrypt_render_certbot_deploy_hook() {
    local env_file
    env_file="$(bt_nginx_ssl_letsencrypt_certbot_env_file_path)"

    cat <<EOF
#!/usr/bin/env bash
set -euo pipefail

ENV_FILE=$(printf '%q' "${env_file}")
[[ -r "\${ENV_FILE}" ]] || {
    printf 'ERROR: missing certbot Cloudflare env file: %s\n' "\${ENV_FILE}" >&2
    exit 1
}

# shellcheck disable=SC1090
source "\${ENV_FILE}"

: "\${RENEWED_LINEAGE:?RENEWED_LINEAGE is required}"

install -d -m 0755 "\$(dirname "\${BT_NGINX_SSL_CERT_PATH}")"
install -m 0644 "\${RENEWED_LINEAGE}/fullchain.pem" "\${BT_NGINX_SSL_CERT_PATH}"
install -m 0600 "\${RENEWED_LINEAGE}/privkey.pem" "\${BT_NGINX_SSL_KEY_PATH}"

if [[ -n "\${BT_NGINX_SSL_NGINX_RELOAD_COMMAND:-}" ]]; then
    /bin/bash -lc "\${BT_NGINX_SSL_NGINX_RELOAD_COMMAND}"
fi
EOF
}

bt_nginx_ssl_letsencrypt_certbot_env_file_path() {
    printf '%s\n' "${BT_NGINX_SSL_CERTBOT_ENV_FILE}"
}

bt_nginx_ssl_letsencrypt_install_support_artifacts() {
    local client
    local auth_hook cleanup_hook deploy_hook env_file

    client="$(bt_nginx_ssl_letsencrypt_resolve_client)"
    env_file="$(bt_nginx_ssl_letsencrypt_certbot_env_file_path)"

    bt_write_file "${env_file}" "$(bt_nginx_ssl_letsencrypt_render_env_file)"
    bt_run chmod 0600 "${env_file}"

    if [[ "${client}" != "certbot" ]]; then
        return 0
    fi

    auth_hook="$(bt_nginx_ssl_letsencrypt_certbot_auth_hook_path)"
    cleanup_hook="$(bt_nginx_ssl_letsencrypt_certbot_cleanup_hook_path)"
    deploy_hook="$(bt_nginx_ssl_letsencrypt_certbot_deploy_hook_path)"

    bt_write_file "${auth_hook}" "$(bt_nginx_ssl_letsencrypt_render_certbot_auth_hook)"
    bt_write_file "${cleanup_hook}" "$(bt_nginx_ssl_letsencrypt_render_certbot_cleanup_hook)"
    bt_write_file "${deploy_hook}" "$(bt_nginx_ssl_letsencrypt_render_certbot_deploy_hook)"
    bt_run chmod 0700 "${auth_hook}" "${cleanup_hook}" "${deploy_hook}"
}

bt_nginx_ssl_letsencrypt_prepare_runtime_paths() {
    bt_mkdir "$(dirname "${BT_NGINX_SSL_CERT_PATH}")"
    bt_mkdir "$(dirname "${BT_NGINX_SSL_KEY_PATH}")"
}

bt_nginx_ssl_letsencrypt_render_renew_command() {
    local client
    local env_file
    local certbot_bin
    local acme_sh_bin
    local inner_command

    client="$(bt_nginx_ssl_letsencrypt_resolve_client)"
    env_file="$(bt_nginx_ssl_letsencrypt_certbot_env_file_path)"

    case "${client}" in
        acme.sh)
            acme_sh_bin="$(bt_nginx_ssl_letsencrypt_resolve_acme_sh_bin)"
            inner_command="set -euo pipefail; source $(printf '%q' "${env_file}"); $(printf '%q' "${acme_sh_bin}") --cron --home $(printf '%q' "${BT_NGINX_SSL_ACME_HOME}") --config-home $(printf '%q' "${BT_NGINX_SSL_ACME_HOME}")"
            ;;
        certbot)
            certbot_bin="$(bt_nginx_ssl_letsencrypt_resolve_certbot_bin)"
            inner_command="set -euo pipefail; source $(printf '%q' "${env_file}"); $(printf '%q' "${certbot_bin}") renew --quiet --config-dir $(printf '%q' "${BT_NGINX_SSL_CERTBOT_CONFIG_DIR}") --work-dir $(printf '%q' "${BT_NGINX_SSL_CERTBOT_WORK_DIR}") --logs-dir $(printf '%q' "${BT_NGINX_SSL_CERTBOT_LOG_DIR}") --manual-auth-hook $(printf '%q' "$(bt_nginx_ssl_letsencrypt_certbot_auth_hook_path)") --manual-cleanup-hook $(printf '%q' "$(bt_nginx_ssl_letsencrypt_certbot_cleanup_hook_path)") --deploy-hook $(printf '%q' "$(bt_nginx_ssl_letsencrypt_certbot_deploy_hook_path)")"
            ;;
        *)
            bt_die "Unsupported ACME client: ${client}"
            ;;
    esac

    printf '/bin/bash -lc %q\n' "${inner_command}"
}

bt_nginx_ssl_letsencrypt_render_renew_cron() {
    cat <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
${BT_NGINX_SSL_RENEW_CRON_SCHEDULE} root $(bt_nginx_ssl_letsencrypt_render_renew_command)
EOF
}

bt_nginx_ssl_letsencrypt_install_renew_cron() {
    [[ "${BT_NGINX_SSL_RENEW_CRON_FILE}" == /etc/cron.d/* ]] && bt_require_root
    bt_nginx_ssl_letsencrypt_validate_hook
    bt_nginx_ssl_letsencrypt_install_support_artifacts
    bt_write_file "${BT_NGINX_SSL_RENEW_CRON_FILE}" "$(bt_nginx_ssl_letsencrypt_render_renew_cron)"
    bt_run chmod 0644 "${BT_NGINX_SSL_RENEW_CRON_FILE}"
}

bt_nginx_ssl_letsencrypt_issue_with_acme_sh() {
    local acme_sh_bin
    local domains=()
    local issue_args=()
    local install_args=()
    local domain

    acme_sh_bin="$(bt_nginx_ssl_letsencrypt_resolve_acme_sh_bin)"
    while IFS= read -r domain; do
        [[ -n "${domain}" ]] || continue
        domains+=("${domain}")
    done < <(bt_nginx_ssl_letsencrypt_collect_domains)
    case "${BT_NGINX_SSL_KEY_TYPE}" in
        ec-256|ec-384)
            install_args+=(--ecc)
            ;;
        rsa-2048|rsa-3072|rsa-4096)
            ;;
        *)
            bt_die "Unsupported BT_NGINX_SSL_KEY_TYPE: ${BT_NGINX_SSL_KEY_TYPE}"
            ;;
    esac

    for domain in "${domains[@]}"; do
        issue_args+=(-d "${domain}")
    done

    bt_nginx_ssl_letsencrypt_prepare_runtime_paths
    bt_nginx_ssl_letsencrypt_install_support_artifacts

    export CF_Token="${CF_Token}"
    export CF_Zone_ID="${CF_Zone_ID}"

    bt_run "${acme_sh_bin}" --set-default-ca --server "${BT_NGINX_SSL_ACME_CA}" --home "${BT_NGINX_SSL_ACME_HOME}" --config-home "${BT_NGINX_SSL_ACME_HOME}"
    bt_run "${acme_sh_bin}" \
        --issue \
        --dns dns_cf \
        --server "${BT_NGINX_SSL_ACME_CA}" \
        --home "${BT_NGINX_SSL_ACME_HOME}" \
        --config-home "${BT_NGINX_SSL_ACME_HOME}" \
        --keylength "${BT_NGINX_SSL_KEY_TYPE}" \
        --dnssleep "${BT_NGINX_SSL_DNS_SLEEP}" \
        "${issue_args[@]}"
    bt_run "${acme_sh_bin}" \
        --install-cert \
        -d "${domains[0]}" \
        --home "${BT_NGINX_SSL_ACME_HOME}" \
        --config-home "${BT_NGINX_SSL_ACME_HOME}" \
        "${install_args[@]}" \
        --key-file "${BT_NGINX_SSL_KEY_PATH}" \
        --fullchain-file "${BT_NGINX_SSL_CERT_PATH}" \
        --reloadcmd "$(bt_nginx_ssl_letsencrypt_render_reload_command)"
}

bt_nginx_ssl_letsencrypt_copy_certbot_lineage() {
    local lineage_dir="$1"

    [[ -d "${lineage_dir}" ]] || bt_die "Certbot lineage is missing: ${lineage_dir}"
    bt_nginx_ssl_letsencrypt_prepare_runtime_paths
    bt_run install -m 0644 "${lineage_dir}/fullchain.pem" "${BT_NGINX_SSL_CERT_PATH}"
    bt_run install -m 0600 "${lineage_dir}/privkey.pem" "${BT_NGINX_SSL_KEY_PATH}"
}

bt_nginx_ssl_letsencrypt_issue_with_certbot() {
    local certbot_bin
    local auth_hook cleanup_hook deploy_hook primary_domain lineage_dir
    local domains=()
    local certbot_domain_args=()
    local certbot_key_args=()
    local certbot_email_args=()
    local domain

    certbot_bin="$(bt_nginx_ssl_letsencrypt_resolve_certbot_bin)"
    while IFS= read -r domain; do
        [[ -n "${domain}" ]] || continue
        domains+=("${domain}")
    done < <(bt_nginx_ssl_letsencrypt_collect_domains)
    case "${BT_NGINX_SSL_KEY_TYPE}" in
        ec-256)
            certbot_key_args+=(--key-type ecdsa --elliptic-curve secp256r1)
            ;;
        ec-384)
            certbot_key_args+=(--key-type ecdsa --elliptic-curve secp384r1)
            ;;
        rsa-2048)
            certbot_key_args+=(--key-type rsa --rsa-key-size 2048)
            ;;
        rsa-3072)
            certbot_key_args+=(--key-type rsa --rsa-key-size 3072)
            ;;
        rsa-4096)
            certbot_key_args+=(--key-type rsa --rsa-key-size 4096)
            ;;
        *)
            bt_die "Unsupported BT_NGINX_SSL_KEY_TYPE: ${BT_NGINX_SSL_KEY_TYPE}"
            ;;
    esac
    bt_nginx_ssl_letsencrypt_install_support_artifacts
    auth_hook="$(bt_nginx_ssl_letsencrypt_certbot_auth_hook_path)"
    cleanup_hook="$(bt_nginx_ssl_letsencrypt_certbot_cleanup_hook_path)"
    deploy_hook="$(bt_nginx_ssl_letsencrypt_certbot_deploy_hook_path)"
    primary_domain="$(bt_nginx_ssl_letsencrypt_primary_certbot_domain)"

    for domain in "${domains[@]}"; do
        certbot_domain_args+=(-d "${domain}")
    done

    if [[ -n "${SSL_ACME_EMAIL:-}" ]]; then
        certbot_email_args+=(--email "${SSL_ACME_EMAIL}")
    else
        certbot_email_args+=(--register-unsafely-without-email)
    fi

    bt_run "${certbot_bin}" certonly \
        --non-interactive \
        --agree-tos \
        --keep-until-expiring \
        --manual \
        --manual-public-ip-logging-ok \
        --preferred-challenges dns \
        --server "${BT_NGINX_SSL_CERTBOT_SERVER}" \
        --config-dir "${BT_NGINX_SSL_CERTBOT_CONFIG_DIR}" \
        --work-dir "${BT_NGINX_SSL_CERTBOT_WORK_DIR}" \
        --logs-dir "${BT_NGINX_SSL_CERTBOT_LOG_DIR}" \
        --manual-auth-hook "${auth_hook}" \
        --manual-cleanup-hook "${cleanup_hook}" \
        --deploy-hook "${deploy_hook}" \
        --cert-name "${primary_domain}" \
        "${certbot_email_args[@]}" \
        "${certbot_key_args[@]}" \
        "${certbot_domain_args[@]}"

    if ! bt_nginx_ssl_letsencrypt_cert_materialized; then
        lineage_dir="${BT_NGINX_SSL_CERTBOT_CONFIG_DIR}/live/${primary_domain}"
        bt_nginx_ssl_letsencrypt_copy_certbot_lineage "${lineage_dir}"
        bt_nginx_ssl_letsencrypt_reload_nginx_gracefully
    fi
}

bt_nginx_ssl_letsencrypt_validate_hook() {
    local client

    bt_nginx_ssl_letsencrypt_validate_domains
    [[ -n "${CF_Token:-}" ]] || bt_die "CF_Token is required for letsencrypt DNS-01 provisioning."
    [[ -n "${CF_Zone_ID:-}" ]] || bt_die "CF_Zone_ID is required for letsencrypt DNS-01 provisioning."

    client="$(bt_nginx_ssl_letsencrypt_resolve_client)"
    command -v openssl >/dev/null 2>&1 || bt_die "openssl is required for letsencrypt provisioning."
    command -v curl >/dev/null 2>&1 || bt_die "curl is required for letsencrypt DNS-01 provisioning."

    case "${client}" in
        acme.sh)
            bt_nginx_ssl_letsencrypt_resolve_acme_sh_bin >/dev/null || bt_die "acme.sh is required but was not found."
            ;;
        certbot)
            bt_nginx_ssl_letsencrypt_resolve_certbot_bin >/dev/null || bt_die "certbot is required but was not found."
            command -v python3 >/dev/null 2>&1 || bt_die "python3 is required for certbot Cloudflare hook rendering."
            ;;
        *)
            bt_die "Unsupported ACME client: ${client}"
            ;;
    esac
}

bt_nginx_ssl_letsencrypt_provision_hook() {
    local client

    bt_nginx_ssl_letsencrypt_validate_hook
    client="$(bt_nginx_ssl_letsencrypt_resolve_client)"

    case "${client}" in
        acme.sh)
            bt_nginx_ssl_letsencrypt_issue_with_acme_sh
            ;;
        certbot)
            bt_nginx_ssl_letsencrypt_issue_with_certbot
            ;;
        *)
            bt_die "Unsupported ACME client: ${client}"
            ;;
    esac
}
