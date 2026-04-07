#!/bin/sh
set -eu

REQUIRED_APPSEC_CONFIG="${CROWDSEC_REQUIRED_APPSEC_CONFIG:-crowdsecurity/appsec-default}"
REQUIRED_APPSEC_COLLECTIONS="${CROWDSEC_REQUIRED_APPSEC_COLLECTIONS:-crowdsecurity/appsec-virtual-patching}"

has_required_appsec_config() {
    cscli appsec-configs list 2>/dev/null | grep -Fq "${REQUIRED_APPSEC_CONFIG}"
}

has_required_appsec_rules() {
    cscli appsec-rules list 2>/dev/null | grep -Fq "crowdsecurity/vpatch-"
}

ensure_required_appsec_config() {
    if has_required_appsec_config && has_required_appsec_rules; then
        return 0
    fi

    echo "Bootstrapping CrowdSec AppSec prerequisites for ${REQUIRED_APPSEC_CONFIG}..."
    cscli hub update >/dev/null 2>&1 || true
    for collection in ${REQUIRED_APPSEC_COLLECTIONS}; do
        cscli collections install "${collection}" --force >/dev/null 2>&1 || true
    done
    cscli appsec-configs install "${REQUIRED_APPSEC_CONFIG}" --force >/dev/null 2>&1 || true

    if ! has_required_appsec_config || ! has_required_appsec_rules; then
        echo "ERROR: required CrowdSec AppSec config or rules are unavailable after bootstrap." >&2
        exit 1
    fi
}

ensure_required_appsec_config

exec /usr/local/bin/crowdsec -c /etc/crowdsec/config.yaml
