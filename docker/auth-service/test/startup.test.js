const test = require("node:test");
const assert = require("node:assert/strict");
const { spawn } = require("node:child_process");
const path = require("node:path");

const serviceDir = path.resolve(__dirname, "..");

function runAuthService(env = {}) {
    return new Promise((resolve, reject) => {
        const child = spawn(process.execPath, ["index.js"], {
            cwd: serviceDir,
            env: {
                ...process.env,
                ...env,
                NODE_ENV: "test",
            },
        });

        let stdout = "";
        let stderr = "";

        child.stdout.on("data", (chunk) => {
            stdout += chunk;
        });
        child.stderr.on("data", (chunk) => {
            stderr += chunk;
        });
        child.on("error", reject);
        child.on("close", (code, signal) => {
            resolve({ code, signal, stdout, stderr });
        });
    });
}

test("refuses to start without SESSION_SECRET", async () => {
    const result = await runAuthService({
        MONITORING_ADMIN_USERNAME: "admin",
        MONITORING_PASSWORD_HASH: "$2b$10$0123456789abcdef0123456789abcdef0123456789abcdef01234",
        SESSION_SECRET: "",
    });

    assert.equal(result.code, 1);
    assert.match(
        result.stderr,
        /SESSION_SECRET is required - refusing to start/,
    );
});
