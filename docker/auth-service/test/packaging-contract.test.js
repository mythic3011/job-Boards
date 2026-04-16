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
