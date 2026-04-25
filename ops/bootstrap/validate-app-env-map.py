#!/usr/bin/env python3

from __future__ import annotations

import json
import sys
from pathlib import Path


NON_REMOVABLE_TEMPLATE_ACTIONS = {
    "keep-normal",
    "advanced-doc",
    "compatibility-only",
}

DERIVED_CLASSIFICATIONS = {
    "compatibility-alias",
    "generated-internal",
}


def load_json(path_text: str) -> dict:
    path = Path(path_text)

    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError as exc:
        raise ValueError(f"Missing JSON file: {path}") from exc
    except json.JSONDecodeError as exc:
        raise ValueError(f"Invalid JSON in {path}: {exc}") from exc

    if not isinstance(payload, dict):
        raise ValueError(f"Expected top-level JSON object in {path}")

    return payload


def fail(message: str) -> int:
    print(message, file=sys.stderr)
    return 1


def require_non_empty_string(entry_name: str, field_name: str, value: object) -> str:
    if not isinstance(value, str) or value.strip() == "":
        raise ValueError(f'{entry_name} must define non-empty "{field_name}"')

    return value


def derive_value(rule: str, canonical_value: str) -> str:
    if rule == "identity":
        return canonical_value

    if rule == "https-url":
        return f"https://{canonical_value}" if canonical_value else ""

    if rule.startswith("path-join:"):
        suffix = rule.split(":", 1)[1]

        if suffix == "":
            return canonical_value

        return str(Path(canonical_value) / Path(suffix))

    raise ValueError(f'Unsupported valueDerivation "{rule}"')


def validate_mapping(mapping: dict) -> tuple[dict[str, dict], list[str]]:
    required_canonical_names = mapping.get("requiredCanonicalNames")
    if not isinstance(required_canonical_names, list) or not required_canonical_names:
        raise ValueError("app-env-map.json must define a non-empty requiredCanonicalNames list")

    required_canonical_names = [require_non_empty_string("requiredCanonicalNames", "value", name) for name in required_canonical_names]

    mappings = mapping.get("mappings")
    if not isinstance(mappings, dict) or not mappings:
        raise ValueError("app-env-map.json must define a non-empty mappings object")

    canonical_entries: dict[str, dict] = {}

    for entry_name, raw_entry in mappings.items():
        if not isinstance(raw_entry, dict):
            raise ValueError(f"{entry_name} must be a JSON object")

        classification = require_non_empty_string(entry_name, "classification", raw_entry.get("classification"))
        canonical_name = require_non_empty_string(entry_name, "canonicalName", raw_entry.get("canonicalName"))
        template_action = require_non_empty_string(entry_name, "templateAction", raw_entry.get("templateAction"))
        require_non_empty_string(entry_name, "valueDerivation", raw_entry.get("valueDerivation"))
        conflict_policy = require_non_empty_string(entry_name, "conflictPolicy", raw_entry.get("conflictPolicy"))

        if template_action in NON_REMOVABLE_TEMPLATE_ACTIONS:
            require_non_empty_string(entry_name, "consumerProof", raw_entry.get("consumerProof"))
            require_non_empty_string(entry_name, "ownerProof", raw_entry.get("ownerProof"))

        if classification == "canonical":
            if entry_name != canonical_name:
                raise ValueError(f'Canonical entry "{entry_name}" must point canonicalName to itself')
            if conflict_policy != "canonical-source-of-truth":
                raise ValueError(f'{entry_name} must declare conflictPolicy "canonical-source-of-truth"')

            canonical_entries[entry_name] = raw_entry
            continue

        if conflict_policy != "must-match-derived-canonical":
            raise ValueError(f'{entry_name} must declare conflictPolicy "must-match-derived-canonical"')

        if canonical_name not in required_canonical_names:
            raise ValueError(f'{entry_name} references unsupported canonical "{canonical_name}"')

        if classification == "generated-internal" and canonical_name != "STATE_DIR":
            raise ValueError(f'{entry_name} must derive from STATE_DIR for generated-internal paths')

        if classification in DERIVED_CLASSIFICATIONS and canonical_name == "STATE_DIR":
            rule = raw_entry["valueDerivation"]
            if entry_name in {"BT_STATE_DIR"} and rule != "identity":
                raise ValueError(f'{entry_name} must use identity derivation from STATE_DIR')
            if entry_name != "BT_STATE_DIR" and not str(rule).startswith("path-join:"):
                raise ValueError(f'{entry_name} must derive with a path-join rule from STATE_DIR')

    if sorted(canonical_entries) != sorted(required_canonical_names):
        raise ValueError(
            "Missing required canonical roots: "
            + ", ".join(sorted(set(required_canonical_names) - set(canonical_entries)))
        )

    return mappings, required_canonical_names


def validate_explicit_values(mappings: dict[str, dict], required_canonical_names: list[str], values: dict) -> dict[str, str]:
    if not isinstance(values, dict):
        raise ValueError("Explicit values must decode to a JSON object")

    explicit_values: dict[str, str] = {}
    for key, raw_value in values.items():
        if not isinstance(raw_value, str):
            raise ValueError(f'Explicit value "{key}" must be a string')
        explicit_values[key] = raw_value

    canonical_values = {
        key: explicit_values[key]
        for key in required_canonical_names
        if key in explicit_values
    }

    errors: list[str] = []
    resolved: dict[str, str] = {}

    for key, value in explicit_values.items():
        entry = mappings.get(key)
        if entry is None:
            continue

        canonical_name = entry["canonicalName"]
        if canonical_name != key and canonical_name not in canonical_values:
            errors.append(
                f'{key} in explicit values requires canonical {canonical_name}; process environment does not count as proof of canonical ownership'
            )

    for canonical_name, canonical_value in canonical_values.items():
        for entry_name, entry in mappings.items():
            if entry["canonicalName"] != canonical_name:
                continue

            resolved[entry_name] = derive_value(entry["valueDerivation"], canonical_value)

    for key, value in explicit_values.items():
        if key not in resolved:
            continue

        expected = resolved[key]
        if value != expected:
            errors.append(f'{key} expected "{expected}" from {mappings[key]["canonicalName"]} but received "{value}"')

    if errors:
        raise ValueError("\n".join(errors))

    return resolved


def main() -> int:
    if len(sys.argv) not in {2, 3}:
        print("Usage: validate-app-env-map.py <app-env-map.json> [explicit-values.json]", file=sys.stderr)
        return 2

    try:
        mapping = load_json(sys.argv[1])
        mappings, required_canonical_names = validate_mapping(mapping)

        if len(sys.argv) == 2:
            return 0

        resolved = validate_explicit_values(mappings, required_canonical_names, load_json(sys.argv[2]))
    except ValueError as exc:
        return fail(str(exc))

    print(json.dumps(resolved, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
