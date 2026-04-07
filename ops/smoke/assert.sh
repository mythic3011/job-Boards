#!/usr/bin/env bash

set -euo pipefail

smoke_fail() {
    printf 'FAIL: %s\n' "$*" >&2
    exit 1
}

smoke_pass() {
    printf 'PASS: %s\n' "$*"
}

smoke_note() {
    printf '%s\n' "$*" >&2
}

require_cmd() {
    local cmd
    for cmd in "$@"; do
        command -v "${cmd}" >/dev/null 2>&1 || smoke_fail "Missing required command: ${cmd}"
    done
}

assert_eq() {
    local expected="$1"
    local actual="$2"
    local message="$3"
    [[ "${expected}" == "${actual}" ]] || smoke_fail "${message} (expected=${expected}, actual=${actual})"
}

assert_nonempty() {
    local value="$1"
    local message="$2"
    [[ -n "${value}" ]] || smoke_fail "${message}"
}

assert_file_contains() {
    local file="$1"
    local pattern="$2"
    local message="$3"
    grep -F "${pattern}" "${file}" >/dev/null 2>&1 || smoke_fail "${message}"
}

assert_jsonl_field() {
    local file="$1"
    local check_id="$2"
    local field_name="$3"
    local expected_value="$4"

    local actual
    actual="$(python3 - "${file}" "${check_id}" "${field_name}" <<'PY'
import json
import sys

path, check_id, field_name = sys.argv[1:]
found = ""
with open(path, "r", encoding="utf-8") as handle:
    for line in handle:
        line = line.strip()
        if not line:
            continue
        record = json.loads(line)
        if record.get("check_id") == check_id:
            found = record.get(field_name, "")
            break
print(found)
PY
)"

    assert_eq "${expected_value}" "${actual}" "Unexpected ${field_name} for ${check_id}"
}

assert_jsonl_status() {
    local file="$1"
    local check_id="$2"
    local expected_status="$3"
    assert_jsonl_field "${file}" "${check_id}" "status" "${expected_status}"
}

assert_jsonl_record_type() {
    local file="$1"
    local check_id="$2"
    local expected_record_type="$3"
    assert_jsonl_field "${file}" "${check_id}" "record_type" "${expected_record_type}"
}
