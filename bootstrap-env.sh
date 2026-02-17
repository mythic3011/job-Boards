#!/bin/bash
set -e
SED_CMD="sed -i"
[[ "$OSTYPE" == "darwin"* ]] && SED_CMD="sed -i ''"
gen_secret() { openssl rand -hex 32; }
gen_app_key() { echo "base64:$(openssl rand -base64 32)"; }
gen_uuid() { command -v uuidgen &>/dev/null && uuidgen | tr '[:upper:]' '[:lower:]' || printf '%08x-%04x-4%03x-%04x-%012x\n' $RANDOM $RANDOM $RANDOM $RANDOM $RANDOM; }
is_weak() {
    local val="$1" var="$2"
    [[ -z "$val" ]] && return 0
    [[ "$var" == "APP_KEY" && ! "$val" =~ ^base64:.{32,}$ ]] && return 0
    [[ "$var" =~ (SECRET|KEY|TOKEN)$ && ${#val} -lt 32 ]] && return 0
    return 1
}
[ ! -f .env.example ] && echo "Error: .env.example not found" && exit 1
[ ! -f .env ] && cp .env.example .env
while IFS= read -r line; do
    [[ "$line" =~ ^#.*$ || -z "$line" ]] && continue
    [[ "$line" =~ ^([A-Z_][A-Z0-9_]*)= ]] || continue
    var="${BASH_REMATCH[1]}"
    val=$(grep "^${var}=" .env 2>/dev/null | cut -d'=' -f2- || echo "")
    if is_weak "$val" "$var"; then
        [[ "$var" == "APP_KEY" ]] && new=$(gen_app_key)
        [[ "$var" =~ (SECRET|KEY|TOKEN)$ ]] && new=$(gen_secret)
        [[ "$var" =~ UUID$ ]] && new=$(gen_uuid)
        [[ -n "$new" ]] && {
            grep -q "^${var}=" .env && eval "$SED_CMD 's|^${var}=.*$|${var}=${new}|' .env" || echo "${var}=${new}" >> .env
        }
    fi
done < .env.example
