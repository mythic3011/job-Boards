const test = require("node:test");
const assert = require("node:assert/strict");
const path = require("node:path");

const {
    createCanonicalAuditMirror,
    loadCanonicalAuditContract,
    normalizeCanonicalRequestId,
    prepareCanonicalAuditMirrorEvent,
} = require("../canonical-audit");

const contractPath = path.resolve(
    __dirname,
    "..",
    "..",
    "..",
    "config",
    "contracts",
    "canonical-audit.v1.json",
);

test("builds reduced canonical payload from shared contract and skips inadmissible events", () => {
    const contract = loadCanonicalAuditContract(contractPath);

    const mirrored = prepareCanonicalAuditMirrorEvent(
        "audit.auth.verify.denied",
        {
            requestId: "abc-123",
            username: "monitoring-admin",
            reason: "invalid_credentials",
            ua: "should-not-pass-through",
        },
        {
            contract,
            now: () => new Date("2026-04-12T00:00:00.000Z"),
        },
    );

    assert.equal(mirrored.action, "mirror");
    assert.deepEqual(mirrored.payload, {
        event_type: "audit.auth.verify.denied",
        request_id: "abc-123",
        source: "auth-service",
        outcome: "denied",
        actor_type: "user",
        target_type: "monitoring_account",
        target_identifier: "monitoring-admin",
        occurred_at: "2026-04-12T00:00:00.000Z",
        metadata: {
            reason: "invalid_credentials",
            username: "monitoring-admin",
        },
    });

    const skipped = prepareCanonicalAuditMirrorEvent(
        "audit.auth.check.success",
        { requestId: "abc-123" },
        { contract },
    );

    assert.deepEqual(skipped, {
        action: "skip",
        reason: "inadmissible_event",
    });
});

test("increments a visible drop counter when admissible events cannot be mirrored", async () => {
    const records = [];
    const mirror = createCanonicalAuditMirror({
        env: {
            CANONICAL_AUDIT_KEY_ID: "",
            CANONICAL_AUDIT_SECRET: "",
            CANONICAL_AUDIT_INGEST_URL: "",
        },
        logger: (level, msg, meta) => records.push({ level, msg, meta }),
        contractPath,
        now: () => new Date("2026-04-12T00:00:00.000Z"),
    });

    const result = await mirror.mirror("audit.auth.verify.denied", {
        requestId: "abc-123",
        username: "monitoring-admin",
        reason: "invalid_credentials",
    });

    assert.deepEqual(result, {
        action: "drop",
        reason: "mirror_not_configured",
    });
    assert.equal(mirror.getDroppedAdmissibleCount(), 1);
    assert.deepEqual(records, [
        {
            level: "error",
            msg: "audit.canonical_mirror.dropped",
            meta: {
                eventType: "audit.auth.verify.denied",
                requestId: "abc-123",
                username: "monitoring-admin",
                mirror_drop_reason: "mirror_not_configured",
                dropped_admissible_count: 1,
            },
        },
    ]);
});

test("normalizes nginx request ids to canonical UUID strings", () => {
    assert.equal(
        normalizeCanonicalRequestId("3a64ad607b4dd5e1a0d6c19d151f1246"),
        "3a64ad60-7b4d-d5e1-a0d6-c19d151f1246",
    );
    assert.equal(
        normalizeCanonicalRequestId("3A64AD60-7B4D-D5E1-A0D6-C19D151F1246"),
        "3a64ad60-7b4d-d5e1-a0d6-c19d151f1246",
    );
    assert.equal(normalizeCanonicalRequestId("external-request-id"), "external-request-id");
});
