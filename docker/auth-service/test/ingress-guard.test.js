const test = require("node:test");
const assert = require("node:assert/strict");
const { spawn } = require("node:child_process");
const path = require("node:path");

const serviceDir = path.resolve(__dirname, "..");

function startAuthService(port, env = {}) {
    return new Promise((resolve, reject) => {
        const child = spawn(process.execPath, ["index.js"], {
            cwd: serviceDir,
            env: {
                ...process.env,
                NODE_ENV: "test",
                PORT: String(port),
                MONITORING_ADMIN_USERNAME: "admin",
                MONITORING_PASSWORD_HASH:
                    "$2b$12$abcdefghijklmnopqrstuu5Lo0g67CiD3M4RpN1BmBb4Crp5w7dbK",
                SESSION_SECRET:
                    "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
                ...env,
            },
            stdio: ["ignore", "pipe", "pipe"],
        });

        let stdout = "";
        let stderr = "";
        let settled = false;

        const finishReject = (error) => {
            if (settled) {
                return;
            }
            settled = true;
            child.kill("SIGTERM");
            reject(error);
        };

        child.stdout.on("data", (chunk) => {
            stdout += chunk.toString();
            if (!settled && stdout.includes('"msg":"auth-service listening"')) {
                settled = true;
                resolve({
                    child,
                    stop: async () => {
                        child.kill("SIGTERM");
                        await new Promise((done) => child.once("close", done));
                    },
                });
            }
        });

        child.stderr.on("data", (chunk) => {
            stderr += chunk.toString();
        });

        child.once("error", finishReject);
        child.once("close", (code) => {
            if (!settled) {
                finishReject(
                    new Error(
                        `auth-service exited early with code ${code}\n${stdout}\n${stderr}`,
                    ),
                );
            }
        });
    });
}

test("denies non-health requests from untrusted proxy peers", async () => {
    const port = 3411;
    const service = await startAuthService(port, {
        AUTH_SERVICE_TRUSTED_PROXY_IPS: "172.30.0.20",
    });

    try {
        const loginResponse = await fetch(
            `http://127.0.0.1:${port}/monitoring/login`,
        );
        const verifyResponse = await fetch(`http://127.0.0.1:${port}/verify`, {
            method: "POST",
            headers: { "content-type": "application/json" },
            body: JSON.stringify({
                username: "admin",
                password: "not-used-in-this-test",
            }),
        });
        const healthResponse = await fetch(`http://127.0.0.1:${port}/health`);

        assert.equal(loginResponse.status, 403);
        assert.equal(verifyResponse.status, 403);
        assert.equal(healthResponse.status, 200);
    } finally {
        await service.stop();
    }
});
