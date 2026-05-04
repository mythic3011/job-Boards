"use strict";

const crypto = require("crypto");
const fs = require("fs");
const path = require("path");

const resolveDefaultContractPath = () => {
    const candidates = [
        // Container runtime layout: /app/config/contracts/...
        path.resolve(__dirname, "config", "contracts", "canonical-audit.v1.json"),
        // Repository test/runtime layout: docker/auth-service/../.. => repo root
        path.resolve(
            __dirname,
            "..",
            "..",
            "config",
            "contracts",
            "canonical-audit.v1.json",
        ),
    ];

    for (const candidate of candidates) {
        if (fs.existsSync(candidate)) {
            return candidate;
        }
    }

    // Keep deterministic fallback for error messages when neither exists.
    return candidates[0];
};

const DEFAULT_CONTRACT_PATH = resolveDefaultContractPath();

function loadCanonicalAuditContract(contractPath = DEFAULT_CONTRACT_PATH) {
    const raw = fs.readFileSync(contractPath, "utf8");
    return JSON.parse(raw);
}

function prepareCanonicalAuditMirrorEvent(
    eventType,
    meta = {},
    { contract, now = () => new Date() } = {},
) {
    const activeContract = contract || loadCanonicalAuditContract();
    const eventDefinition = activeContract?.events?.[eventType];

    if (!eventDefinition?.admissible) {
        return { action: "skip", reason: "inadmissible_event" };
    }

    const requestId = normalizeCanonicalRequestId(meta.requestId);
    if (!requestId) {
        return { action: "drop", reason: "missing_request_id" };
    }

    const target = canonicalTargetForEvent(eventType, meta);
    if (!target.identifier) {
        return { action: "drop", reason: "missing_target_identifier" };
    }

    const metadata = sanitizeMetadata(meta, activeContract);

    return {
        action: "mirror",
        payload: {
            event_type: eventType,
            request_id: requestId,
            source: "auth-service",
            outcome: eventDefinition.outcome,
            actor_type: canonicalActorTypeForEvent(eventType, meta),
            target_type: target.type,
            target_identifier: target.identifier,
            occurred_at: now().toISOString(),
            ...(Object.keys(metadata).length > 0 ? { metadata } : {}),
        },
    };
}

function createCanonicalAuditMirror({
    env = process.env,
    fetchImpl = globalThis.fetch,
    logger = () => {},
    contractPath = DEFAULT_CONTRACT_PATH,
    now = () => new Date(),
} = {}) {
    let droppedAdmissibleCount = 0;

    const config = {
        ingestUrl: stringify(env.CANONICAL_AUDIT_INGEST_URL),
        keyId: stringify(env.CANONICAL_AUDIT_KEY_ID),
        secret: stringify(env.CANONICAL_AUDIT_SECRET),
        timeoutMs: Number.parseInt(env.CANONICAL_AUDIT_TIMEOUT_MS || "1500", 10),
        callerIdentity:
            stringify(env.CANONICAL_AUDIT_CALLER_IDENTITY) ||
            stringify(env.CANONICAL_AUDIT_KEY_ID),
    };

    let contract = null;
    let contractError = null;

    try {
        contract = loadCanonicalAuditContract(contractPath);
    } catch (error) {
        contractError = error;
    }

    const emitDrop = (reason, eventType, meta = {}) => {
        droppedAdmissibleCount += 1;
        logger("error", "audit.canonical_mirror.dropped", {
            eventType,
            requestId: stringify(meta.requestId),
            username: stringify(meta.username),
            mirror_drop_reason: reason,
            dropped_admissible_count: droppedAdmissibleCount,
        });
    };

    const mirror = async (eventType, meta = {}) => {
        if (contractError) {
            emitDrop("contract_unavailable", eventType, meta);
            return { action: "drop", reason: "contract_unavailable" };
        }

        const prepared = prepareCanonicalAuditMirrorEvent(eventType, meta, {
            contract,
            now,
        });

        if (prepared.action !== "mirror") {
            if (prepared.action === "drop") {
                emitDrop(prepared.reason, eventType, meta);
            }

            return prepared;
        }

        if (!config.ingestUrl || !config.keyId || !config.secret) {
            emitDrop("mirror_not_configured", eventType, meta);
            return { action: "drop", reason: "mirror_not_configured" };
        }

        if (typeof fetchImpl !== "function") {
            emitDrop("fetch_unavailable", eventType, meta);
            return { action: "drop", reason: "fetch_unavailable" };
        }

        const payload = attachCallerIdentity(prepared.payload, config.callerIdentity);
        const body = JSON.stringify(payload);
        const signature = crypto
            .createHmac("sha256", config.secret)
            .update(body)
            .digest("hex");

        const abortController = new AbortController();
        const timeout = setTimeout(
            () => abortController.abort(),
            Number.isFinite(config.timeoutMs) ? config.timeoutMs : 1500,
        );

        try {
            const response = await fetchImpl(config.ingestUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Canonical-Audit-Key-Id": config.keyId,
                    "X-Canonical-Audit-Signature": signature,
                },
                body,
                signal: abortController.signal,
            });

            if (response.ok) {
                return { action: "mirror", reason: "delivered" };
            }

            const reason =
                response.status >= 500
                    ? "upstream_5xx"
                    : `upstream_${response.status}`;

            emitDrop(reason, eventType, meta);
            return { action: "drop", reason };
        } catch (error) {
            const reason =
                error?.name === "AbortError" ? "timeout" : "network_error";

            emitDrop(reason, eventType, meta);
            return { action: "drop", reason };
        } finally {
            clearTimeout(timeout);
        }
    };

    return {
        mirror,
        getDroppedAdmissibleCount: () => droppedAdmissibleCount,
    };
}

function sanitizeMetadata(meta, contract) {
    const allowedKeys = new Set(contract?.metadata?.allowed_keys || []);
    const maxKeys = contract?.metadata?.max_keys || 0;
    const maxValueLength = contract?.metadata?.max_value_length || 0;

    const candidateEntries = [
        ["reason", meta.reason],
        ["username", meta.username],
        ["rate_limit_bucket", meta.rateLimitBucket],
        ["target_hint", meta.targetHint],
    ];

    const sanitized = {};

    for (const [key, value] of candidateEntries) {
        if (!allowedKeys.has(key)) {
            continue;
        }

        const normalizedValue = stringify(value);
        if (!normalizedValue) {
            continue;
        }

        sanitized[key] = normalizedValue.slice(0, maxValueLength);

        if (Object.keys(sanitized).length >= maxKeys) {
            break;
        }
    }

    return sanitized;
}

function attachCallerIdentity(payload, callerIdentity) {
    const normalizedCallerIdentity = stringify(callerIdentity);
    if (!normalizedCallerIdentity) {
        return payload;
    }

    const metadata = {
        ...(payload.metadata || {}),
        caller_identity: normalizedCallerIdentity,
    };

    return {
        ...payload,
        metadata,
    };
}

function normalizeCanonicalRequestId(value) {
    const requestId = stringify(value);
    if (!requestId) {
        return "";
    }

    if (
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(
            requestId,
        )
    ) {
        return requestId.toLowerCase();
    }

    if (/^[0-9a-f]{32}$/i.test(requestId)) {
        const normalized = requestId.toLowerCase();
        return [
            normalized.slice(0, 8),
            normalized.slice(8, 12),
            normalized.slice(12, 16),
            normalized.slice(16, 20),
            normalized.slice(20),
        ].join("-");
    }

    return requestId;
}

function canonicalActorTypeForEvent(eventType, meta) {
    if (eventType === "audit.auth.verify.success") {
        return "user";
    }

    return stringify(meta.username) ? "user" : "guest";
}

function canonicalTargetForEvent(eventType, meta) {
    if (eventType.startsWith("audit.auth.verify.")) {
        return {
            type: "monitoring_account",
            identifier: stringify(meta.username) || "unknown-account",
        };
    }

    if (eventType.startsWith("audit.auth.check.")) {
        return {
            type: "monitoring_session",
            identifier:
                stringify(meta.username) ||
                stringify(meta.reason) ||
                "monitoring-session",
        };
    }

    if (eventType === "audit.auth.logout") {
        return {
            type: "monitoring_session",
            identifier:
                stringify(meta.username) ||
                stringify(meta.outcome) ||
                "monitoring-session",
        };
    }

    if (eventType === "audit.auth.rate_limit.triggered") {
        return {
            type: "monitoring_endpoint",
            identifier: stringify(meta.rateLimitBucket) || "auth-endpoint",
        };
    }

    return {
        type: "audit_event",
        identifier: eventType,
    };
}

function stringify(value) {
    if (typeof value !== "string") {
        return "";
    }

    return value.trim();
}

module.exports = {
    createCanonicalAuditMirror,
    loadCanonicalAuditContract,
    normalizeCanonicalRequestId,
    prepareCanonicalAuditMirrorEvent,
};
