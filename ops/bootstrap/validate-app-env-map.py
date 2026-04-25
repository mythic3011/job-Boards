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

ALLOWED_CLASSIFICATIONS = {
    "canonical",
    "compatibility-alias",
    "generated-internal",
}

ALLOWED_OWNERSHIP = {
    "operator",
    "profile",
    "bootstrap",
    "internal",
}

ALLOWED_LIFECYCLE = {
    "required",
    "defaulted",
    "generated",
    "derived",
    "injected",
}

ALLOWED_TEMPLATE_ACTIONS = {
    "keep-normal",
    "advanced-doc",
    "compatibility-only",
    "remove-normal",
}

ALLOWED_CONFLICT_POLICIES = {
    "canonical-source-of-truth",
    "must-match-derived-canonical",
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


def require_enum_member(entry_name: str, field_name: str, value: str, allowed_values: set[str]) -> str:
    if value not in allowed_values:
        raise ValueError(
            f'{entry_name} has invalid "{field_name}" "{value}"; allowed values: {", ".join(sorted(allowed_values))}'
        )

    return value


def validate_value_derivation(entry_name: str, value: str) -> str:
    if value in {"identity", "https-url"}:
        return value

    if value.startswith("path-join:"):
        return value

    raise ValueError(f'{entry_name} has unsupported "valueDerivation" "{value}"')


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


def validate_mapping(mapping: dict) -> tuple[dict[str, dict], list[str], set[str]]:
    required_canonical_names = mapping.get("requiredCanonicalNames")
    if not isinstance(required_canonical_names, list) or not required_canonical_names:
        raise ValueError("app-env-map.json must define a non-empty requiredCanonicalNames list")

    required_canonical_names = [require_non_empty_string("requiredCanonicalNames", "value", name) for name in required_canonical_names]
    if len(set(required_canonical_names)) != len(required_canonical_names):
        raise ValueError("app-env-map.json must not repeat entries in requiredCanonicalNames")

    secret_semantic_roles = mapping.get("secretSemanticRoles")
    if not isinstance(secret_semantic_roles, list) or not secret_semantic_roles:
        raise ValueError("app-env-map.json must define a non-empty secretSemanticRoles list")

    secret_semantic_roles = {
        require_non_empty_string("secretSemanticRoles", "value", role)
        for role in secret_semantic_roles
    }

    mappings = mapping.get("mappings")
    if not isinstance(mappings, dict) or not mappings:
        raise ValueError("app-env-map.json must define a non-empty mappings object")

    normalized_entries: dict[str, dict] = {}

    for entry_name, raw_entry in mappings.items():
        if not isinstance(raw_entry, dict):
            raise ValueError(f"{entry_name} must be a JSON object")

        name = require_non_empty_string(entry_name, "name", raw_entry.get("name"))
        if name != entry_name:
            raise ValueError(f'{entry_name} must define "name" matching its mapping key')

        semantic_role = require_non_empty_string(entry_name, "semanticRole", raw_entry.get("semanticRole"))
        classification = require_enum_member(
            entry_name,
            "classification",
            require_non_empty_string(entry_name, "classification", raw_entry.get("classification")),
            ALLOWED_CLASSIFICATIONS,
        )
        canonical_name = require_non_empty_string(entry_name, "canonicalName", raw_entry.get("canonicalName"))
        ownership = require_enum_member(
            entry_name,
            "ownership",
            require_non_empty_string(entry_name, "ownership", raw_entry.get("ownership")),
            ALLOWED_OWNERSHIP,
        )
        lifecycle = require_enum_member(
            entry_name,
            "lifecycle",
            require_non_empty_string(entry_name, "lifecycle", raw_entry.get("lifecycle")),
            ALLOWED_LIFECYCLE,
        )
        template_action = require_enum_member(
            entry_name,
            "templateAction",
            require_non_empty_string(entry_name, "templateAction", raw_entry.get("templateAction")),
            ALLOWED_TEMPLATE_ACTIONS,
        )
        value_derivation = validate_value_derivation(
            entry_name,
            require_non_empty_string(entry_name, "valueDerivation", raw_entry.get("valueDerivation")),
        )
        conflict_policy = require_enum_member(
            entry_name,
            "conflictPolicy",
            require_non_empty_string(entry_name, "conflictPolicy", raw_entry.get("conflictPolicy")),
            ALLOWED_CONFLICT_POLICIES,
        )

        if template_action in NON_REMOVABLE_TEMPLATE_ACTIONS:
            require_non_empty_string(entry_name, "consumerProof", raw_entry.get("consumerProof"))
            require_non_empty_string(entry_name, "ownerProof", raw_entry.get("ownerProof"))

        if ownership == "internal" and template_action in {"keep-normal", "advanced-doc"}:
            raise ValueError(
                f'{entry_name} cannot use ownership "{ownership}" with templateAction "{template_action}"; '
                "internal values must stay out of operator-facing templates"
            )

        if lifecycle == "required" and template_action == "remove-normal":
            raise ValueError(
                f'{entry_name} cannot use lifecycle "{lifecycle}" with templateAction "{template_action}"'
            )

        if lifecycle in {"generated", "injected"} and template_action == "keep-normal" and semantic_role in secret_semantic_roles:
            raise ValueError(
                f'{entry_name} cannot use lifecycle "{lifecycle}" with templateAction "{template_action}" '
                "for a generated or injected secret-like value"
            )

        normalized_entries[entry_name] = {
            **raw_entry,
            "semanticRole": semantic_role,
            "classification": classification,
            "canonicalName": canonical_name,
            "ownership": ownership,
            "lifecycle": lifecycle,
            "templateAction": template_action,
            "valueDerivation": value_derivation,
            "conflictPolicy": conflict_policy,
        }

    canonical_entries = {
        entry_name: entry
        for entry_name, entry in normalized_entries.items()
        if entry["classification"] == "canonical"
    }

    for required_canonical_name in required_canonical_names:
        entry = normalized_entries.get(required_canonical_name)
        if entry is None or entry["classification"] != "canonical":
            raise ValueError(f"Missing required canonical roots: {required_canonical_name}")

    for entry_name, entry in normalized_entries.items():
        classification = entry["classification"]
        canonical_name = entry["canonicalName"]
        conflict_policy = entry["conflictPolicy"]
        value_derivation = entry["valueDerivation"]

        if classification == "canonical":
            if canonical_name != entry_name:
                raise ValueError(f'Canonical entry "{entry_name}" must point canonicalName to itself')
            if conflict_policy != "canonical-source-of-truth":
                raise ValueError(f'{entry_name} must declare conflictPolicy "canonical-source-of-truth"')
            if value_derivation != "identity":
                raise ValueError(f'{entry_name} must use "identity" valueDerivation because it is canonical')
            continue

        if canonical_name not in canonical_entries:
            raise ValueError(f'{entry_name} references non-canonical "{canonical_name}"')
        if conflict_policy != "must-match-derived-canonical":
            raise ValueError(f'{entry_name} must declare conflictPolicy "must-match-derived-canonical"')

    return normalized_entries, required_canonical_names, secret_semantic_roles


def validate_explicit_values(mappings: dict[str, dict], required_canonical_names: list[str], values: dict) -> dict[str, str]:
    if not isinstance(values, dict):
        raise ValueError("Explicit values must decode to a JSON object")

    explicit_values: dict[str, str] = {}
    for key, raw_value in values.items():
        if key not in mappings:
            raise ValueError(f'Explicit value "{key}" is not declared in app-env-map.json')
        if not isinstance(raw_value, str):
            raise ValueError(f'Explicit value "{key}" must be a string')
        explicit_values[key] = raw_value

    canonical_entry_names = {
        entry_name
        for entry_name, entry in mappings.items()
        if entry["classification"] == "canonical"
    }

    canonical_values = {
        key: explicit_values[key]
        for key in canonical_entry_names
        if key in explicit_values
    }

    errors: list[str] = []
    resolved: dict[str, str] = {}

    for key, value in explicit_values.items():
        entry = mappings.get(key)
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
        mappings, required_canonical_names, _secret_semantic_roles = validate_mapping(mapping)

        if len(sys.argv) == 2:
            return 0

        resolved = validate_explicit_values(mappings, required_canonical_names, load_json(sys.argv[2]))
    except ValueError as exc:
        return fail(str(exc))

    print(json.dumps(resolved, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
