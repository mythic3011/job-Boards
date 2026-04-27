const test = require("node:test");
const assert = require("node:assert/strict");
const { spawn } = require("node:child_process");
const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");

const serviceDir = path.resolve(__dirname, "..");

function makeFixturePublicDir() {
    const fixtureDir = fs.mkdtempSync(
        path.join(os.tmpdir(), "auth-service-public-"),
    );

    fs.mkdirSync(path.join(fixtureDir, "assets"), { recursive: true });
    fs.mkdirSync(path.join(fixtureDir, "icons", "services"), {
        recursive: true,
    });
    fs.writeFileSync(
        path.join(fixtureDir, "index.html"),
        `<!doctype html>
<html lang="en">
  <head>
    <script type="module" src="/monitoring/assets/index-test.js"></script>
    <link rel="stylesheet" href="/monitoring/assets/index-test.css">
  </head>
  <body><div id="root">Loading…</div></body>
</html>`,
    );
    fs.writeFileSync(
        path.join(fixtureDir, "assets", "index-test.css"),
        "body{background:#111;}",
    );
    fs.writeFileSync(
        path.join(fixtureDir, "assets", "index-test.js"),
        "console.log('ok');",
    );
    fs.writeFileSync(
        path.join(fixtureDir, "icons", "services", "nginx.svg"),
        "<svg></svg>",
    );

    return fixtureDir;
}

function startAuthService(port, publicDir) {
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
                AUTH_SERVICE_PUBLIC_DIR: publicDir,
                AUTH_SERVICE_ENFORCE_TRUSTED_PROXY: "false",
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
                    new Error(`auth-service exited early with code ${code}\n${stdout}\n${stderr}`),
                );
            }
        });
    });
}

test("serves prefixed monitoring login assets and icons", async () => {
    const port = 3410;
    const fixturePublicDir = makeFixturePublicDir();
    const service = await startAuthService(port, fixturePublicDir);

    try {
        const loginResponse = await fetch(
            `http://127.0.0.1:${port}/monitoring/login`,
        );
        const loginHtml = await loginResponse.text();

        assert.equal(loginResponse.status, 200);
        assert.match(loginHtml, /\/monitoring\/assets\/index-.*\.js/);
        assert.match(loginHtml, /\/monitoring\/assets\/index-.*\.css/);

        const cssResponse = await fetch(
            `http://127.0.0.1:${port}/monitoring/assets/index-test.css`,
        );
        const iconResponse = await fetch(
            `http://127.0.0.1:${port}/monitoring/icons/services/nginx.svg`,
        );

        assert.equal(cssResponse.status, 200);
        assert.equal(iconResponse.status, 200);
    } finally {
        await service.stop();
        fs.rmSync(fixturePublicDir, { recursive: true, force: true });
    }
});
