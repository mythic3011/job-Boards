#!/usr/bin/env bash

bt_config_contract_resolver() {
    printf '%s\n' "${BT_ROOT_DIR}/ops/bin/resolve-config-contract"
}

bt_config_resolve_key() {
    local key="$1"
    python3 "$(bt_config_contract_resolver)" key "${key}"
}

bt_config_export_defaults() {
    eval "$(python3 "$(bt_config_contract_resolver)" shell)"
}

bt_config_write_dotenv() {
    local destination="$1"
    python3 "$(bt_config_contract_resolver)" dotenv > "${destination}"
}
