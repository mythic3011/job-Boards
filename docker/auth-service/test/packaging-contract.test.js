"use strict";

const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const repoRoot = path.resolve(__dirname, "..", "..", "..");

test("auth-service packaging copies canonical audit runtime files from repo root", () => {
    const dockerfile = fs.readFileSync(
        path.join(repoRoot, "docker", "auth-service", "Dockerfile"),
        "utf8",
    );

    assert.ok(
        dockerfile.includes("COPY docker/auth-service/canonical-audit.js ./"),
        "Dockerfile must copy canonical-audit.js into the runtime image",
    );
    assert.ok(
        dockerfile.includes("COPY docker/auth-service/client-ip.js ./"),
        "Dockerfile must copy client-ip.js into the runtime image",
    );
    assert.ok(
        dockerfile.includes(
            "COPY config/contracts/canonical-audit.v1.json ./config/contracts/canonical-audit.v1.json",
        ),
        "Dockerfile must copy the shared canonical audit contract into the runtime image",
    );
});

test("compose files build auth-service from repo root so shared contract artifacts stay available", () => {
    const composeObs = fs.readFileSync(
        path.join(repoRoot, "compose.obs.yml"),
        "utf8",
    );
    const composeDev = fs.readFileSync(
        path.join(repoRoot, "compose.yaml"),
        "utf8",
    );

    for (const composeContents of [composeObs, composeDev]) {
        assert.ok(
            composeContents.includes("context: ."),
            "Compose auth-service build must use repo root as context",
        );
        assert.ok(
            composeContents.includes("dockerfile: docker/auth-service/Dockerfile"),
            "Compose auth-service build must point at docker/auth-service/Dockerfile",
        );
    }
});

test("auth-service log volume init builds the local auth-service image without remote pulls", () => {
    const composeFiles = [
        ["compose.yaml", "    auth-service-logs-init:", "    auth-service:"],
        ["compose.obs.yml", "  auth-service-logs-init:", "  auth-service:"],
    ];

    for (const [file, initMarker, serviceMarker] of composeFiles) {
        const composeContents = fs.readFileSync(path.join(repoRoot, file), "utf8");
        const logsInitStart = composeContents.indexOf(initMarker);
        const authServiceStart = composeContents.indexOf(
            serviceMarker,
            logsInitStart + initMarker.length,
        );
        const logsInitBlock = composeContents.slice(logsInitStart, authServiceStart);

        assert.ok(logsInitStart >= 0, `${file} must define auth-service-logs-init`);
        assert.ok(
            logsInitBlock.includes("pull_policy: never"),
            `${file} auth-service-logs-init must refuse remote image pulls`,
        );
        assert.ok(
            logsInitBlock.includes("context: ."),
            `${file} auth-service-logs-init must build from repo root`,
        );
        assert.ok(
            logsInitBlock.includes("dockerfile: docker/auth-service/Dockerfile"),
            `${file} auth-service-logs-init must build the local auth-service image`,
        );
        assert.ok(
            logsInitBlock.includes("-auth-service"),
            `${file} auth-service-logs-init must use the local auth-service image tag`,
        );
    }
});

test("auth-service startup loads generated dotenv values without shell expansion", () => {
    const composeObs = fs.readFileSync(
        path.join(repoRoot, "compose.obs.yml"),
        "utf8",
    );
    const composeDev = fs.readFileSync(
        path.join(repoRoot, "compose.yaml"),
        "utf8",
    );

    for (const composeContents of [composeObs, composeDev]) {
        assert.ok(
            composeContents.includes('while IFS= read -r line || [ -n "$$line" ]; do'),
            "Generated obs env must be parsed as raw dotenv so bcrypt hashes keep literal $ characters",
        );
        assert.ok(
            !composeContents.includes('. "$${GENERATED_ENV_FILE}"'),
            "Generated obs dotenv must not be sourced as shell because bcrypt $2y$ hashes are expanded",
        );
        assert.ok(
            composeContents.includes(
                'export CANONICAL_AUDIT_SECRET="$${CANONICAL_AUDIT_AUTH_SERVICE_SECRET}"',
            ),
            "Auth service must map the generated canonical audit secret to the Node runtime key",
        );
    }
});

test("auth-service canonical audit mirror targets nginx over trusted internal TLS by default", () => {
    const composeObs = fs.readFileSync(
        path.join(repoRoot, "compose.obs.yml"),
        "utf8",
    );
    const composeDev = fs.readFileSync(
        path.join(repoRoot, "compose.yaml"),
        "utf8",
    );

    for (const composeContents of [composeObs, composeDev]) {
        assert.ok(
            composeContents.includes(
                "https://nginx/api/internal/canonical-audit/events",
            ),
            "Auth service mirror must use the nginx HTTP entrypoint, not the Laravel FPM service",
        );
        assert.ok(
            composeContents.includes(
                "/var/lib/blue-team-vm/runtime/nginx-ssl/selfsigned.crt",
            ),
            "Auth service must trust the mounted local nginx self-signed certificate for internal mirror delivery",
        );
    }
});
