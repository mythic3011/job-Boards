#!/usr/bin/env python3

from __future__ import annotations

import json
import sys
from pathlib import Path


REQUIRED_KEYS = [
    "modeDefaults",
    "protectedCanonicalValues",
    "allowedOverrides",
    "outputFilenames",
    "secretSourceOrder",
    "conflictHandlingPolicy",
    "legacyMappings",
    "migrationMessageKeys",
    "resetDemoBehavior",
]


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: validate-contract.py <contract.json>", file=sys.stderr)
        return 2

    path = Path(sys.argv[1])
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        print(f"Missing contract file: {path}", file=sys.stderr)
        return 1
    except json.JSONDecodeError as exc:
        print(f"Invalid JSON in {path}: {exc}", file=sys.stderr)
        return 1

    missing = [key for key in REQUIRED_KEYS if key not in payload]
    if missing:
        print("Missing required contract sections: " + ", ".join(missing), file=sys.stderr)
        return 1

    if payload["resetDemoBehavior"] != "validate-only-stop":
        print("resetDemoBehavior must be validate-only-stop for PR2A", file=sys.stderr)
        return 1

    if not isinstance(payload["legacyMappings"], list) or not payload["legacyMappings"]:
        print("legacyMappings must be a non-empty list", file=sys.stderr)
        return 1

    if not isinstance(payload["migrationMessageKeys"], dict) or not payload["migrationMessageKeys"]:
        print("migrationMessageKeys must be a non-empty object", file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
