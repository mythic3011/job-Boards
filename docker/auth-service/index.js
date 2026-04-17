"use strict";

const crypto = require("crypto");
const fs = require("fs");
const path = require("path");
const { createCanonicalAuditMirror } = require("./canonical-audit");
const {
    createCanonicalClientIpKeyGenerator,
    createClientIpResolver,
    parseTrustedProxyIps,
} = require("./client-ip");

const AUTH_SERVICE_LOG_FILE = process.env.AUTH_SERVICE_LOG_FILE?.trim() || "";

if (AUTH_SERVICE_LOG_FILE) {
    try {
        fs.mkdirSync(path.dirname(AUTH_SERVICE_LOG_FILE), { recursive: true });
    } catch (error) {
        process.stderr.write(
            `[auth-service] failed to prepare log path: ${error.message}\n`,
        );
    }
}

// ── Logger ────────────────────────────────────────────────────────────────────
const writeLog = (level, msg, meta = {}) => {
    const line = JSON.stringify({
        ts: new Date().toISOString(),
        level,
        msg,
        ...meta,
    });

    console.log(line);

    if (AUTH_SERVICE_LOG_FILE) {
        fs.appendFile(AUTH_SERVICE_LOG_FILE, `${line}\n`, (error) => {
            if (error) {
                process.stderr.write(
                    `[auth-service] failed to append log file: ${error.message}\n`,
                );
            }
        });
    }
};

const canonicalAuditMirror = createCanonicalAuditMirror({
    logger: writeLog,
});

const log = (level, msg, meta = {}) => {
    writeLog(level, msg, meta);
    void canonicalAuditMirror.mirror(msg, meta);
};

// ── Config ────────────────────────────────────────────────────────────────────
const USERS = {
    [process.env.MONITORING_ADMIN_USERNAME]:
        process.env.MONITORING_PASSWORD_HASH || "",
};

const SESSION_TTL = 8 * 60 * 60 * 1000; // 8h
const SESSION_SECRET = process.env.SESSION_SECRET?.trim() || "";
const TRUSTED_PROXY_IPS = parseTrustedProxyIps(
    process.env.AUTH_SERVICE_TRUSTED_PROXY_IPS || "",
);

const failStartup = (message) => {
    log("error", message);
    throw new Error(message);
};

if (!process.env.MONITORING_ADMIN_USERNAME) {
    log("warn", "MONITORING_ADMIN_USERNAME not set — all logins will fail");
}

if (!USERS[process.env.MONITORING_ADMIN_USERNAME]) {
    log("warn", "MONITORING_PASSWORD_HASH not set — all logins will fail");
}
if (!SESSION_SECRET) {
    failStartup("SESSION_SECRET is required - refusing to start");
}

const express = require("express");
const bcrypt = require("bcrypt");
const cookieParser = require("cookie-parser");
const rateLimit = require("express-rate-limit");

const app = express();
app.use(express.json());
app.use(cookieParser());

// serve built frontend assets if present
app.use(express.static(path.join(__dirname, "public")));

// fallback for client-side routing (login page)
app.get(["/", "/login"], (_req, res) => {
    res.sendFile(path.join(__dirname, "public", "index.html"));
});

// ── In-memory session store ───────────────────────────────────────────────────
// Structure: token → { username, expires, ip, ua }
const sessions = new Map();

// Prune expired sessions every 15 min
setInterval(
    () => {
        const now = Date.now();
        let pruned = 0;
        for (const [token, s] of sessions) {
            if (s.expires < now) {
                sessions.delete(token);
                pruned++;
            }
        }
        if (pruned > 0) log("info", "pruned expired sessions", { pruned });
    },
    15 * 60 * 1000,
);

// ── HMAC token signing ────────────────────────────────────────────────────────
const signToken = (token) => {
    const hmac = crypto
        .createHmac("sha256", SESSION_SECRET)
        .update(token)
        .digest("hex");
    return `${token}.${hmac}`;
};

const verifyToken = (signed) => {
    if (!signed) return null;
    const dot = signed.lastIndexOf(".");
    if (dot === -1) return null;
    const token = signed.slice(0, dot);
    const expected = crypto
        .createHmac("sha256", SESSION_SECRET)
        .update(token)
        .digest("hex");
    const actual = signed.slice(dot + 1);
    // Constant-time compare
    if (actual.length !== expected.length) return null;
    const a = Buffer.from(actual);
    const b = Buffer.from(expected);
    if (!crypto.timingSafeEqual(a, b)) return null;
    return token;
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const getClientIP = createClientIpResolver({
    trustedProxyIps: TRUSTED_PROXY_IPS,
});
const canonicalClientIpKeyGenerator =
    createCanonicalClientIpKeyGenerator(getClientIP);

const getAuditMeta = (req, extra = {}) => ({
    ip: getClientIP(req),
    ua: req.headers["user-agent"] || "",
    requestId: req.headers["x-request-id"] || "",
    ...extra,
});

const normalizeBcryptHash = (hash) => {
    if (!hash) return hash;
    // Apache htpasswd emits $2y$, while node-bcrypt expects $2a$/$2b$.
    return hash.replace(/^\$2y\$/, "$2b$");
};

// ── Rate limiters ─────────────────────────────────────────────────────────────
// /verify: max 10 attempts per 15 min per IP
const verifyLimiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 10,
    standardHeaders: true,
    legacyHeaders: false,
    keyGenerator: canonicalClientIpKeyGenerator,
    handler: (req, res) => {
        log("warn", "audit.auth.rate_limit.triggered", {
            ...getAuditMeta(req),
            rateLimitBucket: "verify",
            reason: "verify_rate_limit_exceeded",
        });
        res.status(429).json({ error: "Too many attempts. Try again later." });
    },
});

// /check: max 120 per min per IP (normal browser polling)
const checkLimiter = rateLimit({
    windowMs: 60 * 1000,
    max: 120,
    standardHeaders: true,
    legacyHeaders: false,
    keyGenerator: canonicalClientIpKeyGenerator,
    skipSuccessfulRequests: true, // only count failures
    handler: (req, res) => {
        log("warn", "audit.auth.rate_limit.triggered", {
            ...getAuditMeta(req),
            rateLimitBucket: "check",
            reason: "check_rate_limit_exceeded",
        });
        res.status(429).end();
    },
});

// ── Routes ────────────────────────────────────────────────────────────────────
app.get("/health", (_req, res) =>
    res.json({
        status: "ok",
        sessions: sessions.size,
        canonicalAuditMirrorDrops:
            canonicalAuditMirror.getDroppedAdmissibleCount(),
    }),
);

app.post("/verify", verifyLimiter, async (req, res) => {
    const { username, password } = req.body || {};
    const meta = getAuditMeta(req, { username: username || "" });

    if (!username || !password) {
        log("warn", "audit.auth.verify.denied", {
            ...meta,
            reason: "missing_credentials",
        });
        return res.status(400).json({ error: "Missing credentials" });
    }

    const hash = normalizeBcryptHash(USERS[username]);
    let valid = false;
    try {
        valid = hash ? await bcrypt.compare(password, hash) : false;
    } catch (err) {
        log("error", "audit.auth.verify.error", {
            ...meta,
            reason: "bcrypt_compare_failed",
            err: err.message,
        });
        return res.status(500).json({ error: "Internal error" });
    }

    if (!valid) {
        log("warn", "audit.auth.verify.denied", {
            ...meta,
            reason: "invalid_credentials",
        });
        // Delay to slow brute force even after rate limit resets
        await new Promise((r) => setTimeout(r, 500));
        return res.status(401).json({ error: "Invalid credentials" });
    }

    const rawToken = crypto.randomBytes(32).toString("hex");
    const signedToken = signToken(rawToken);

    sessions.set(rawToken, {
        username,
        expires: Date.now() + SESSION_TTL,
        ip: meta.ip,
        ua: req.headers["user-agent"] || "",
    });

    log("info", "audit.auth.verify.success", {
        ...meta,
        sessions: sessions.size,
    });

    res.cookie("monitoring_session", signedToken, {
        httpOnly: true,
        secure: true,
        sameSite: "strict",
        maxAge: SESSION_TTL,
    });
    res.json({ ok: true });
});

app.get("/check", checkLimiter, (req, res) => {
    const signedToken = req.cookies?.monitoring_session;
    const meta = getAuditMeta(req);

    if (!signedToken) {
        log("warn", "audit.auth.check.denied", {
            ...meta,
            reason: "missing_session_cookie",
        });
        return res.status(401).end();
    }

    const rawToken = verifyToken(signedToken);

    if (!rawToken) {
        log("warn", "audit.auth.check.denied", {
            ...meta,
            reason: "invalid_session_signature",
        });
        return res.status(401).end();
    }

    const session = sessions.get(rawToken);

    if (!session) {
        log("warn", "audit.auth.check.denied", {
            ...meta,
            reason: "session_not_found",
        });
        return res.status(401).end();
    }

    if (session.expires < Date.now()) {
        sessions.delete(rawToken);
        log("info", "audit.auth.check.denied", {
            ...meta,
            reason: "session_expired",
            username: session.username,
        });
        return res.status(401).end();
    }

    // IP binding — reject if IP changed
    if (session.ip !== meta.ip) {
        sessions.delete(rawToken);
        log("warn", "audit.auth.check.denied", {
            ...meta,
            reason: "ip_mismatch",
            expected: session.ip,
            got: meta.ip,
            username: session.username,
        });
        return res.status(401).end();
    }

    // Refresh TTL on valid check
    session.expires = Date.now() + SESSION_TTL;
    log("info", "audit.auth.check.success", {
        ...meta,
        username: session.username,
    });
    res.status(200).end();
});

app.post("/logout", (req, res) => {
    const signedToken = req.cookies?.monitoring_session;
    const meta = getAuditMeta(req);

    if (!signedToken) {
        log("info", "audit.auth.logout", {
            ...meta,
            outcome: "no_cookie",
        });
    }

    const rawToken = verifyToken(signedToken);

    if (rawToken) {
        const session = sessions.get(rawToken);
        log("info", "audit.auth.logout", {
            ...meta,
            outcome: session ? "session_revoked" : "token_not_found",
            username: session?.username || "",
        });
        sessions.delete(rawToken);
    } else if (signedToken) {
        log("warn", "audit.auth.logout", {
            ...meta,
            outcome: "invalid_cookie_signature",
        });
    }

    res.clearCookie("monitoring_session", {
        httpOnly: true,
        secure: true,
        sameSite: "strict",
    });
    res.redirect("/monitoring/login");
});

// ── Graceful shutdown ─────────────────────────────────────────────────────────
const shutdown = (signal) => {
    log("info", `${signal} received — shutting down`, {
        sessions: sessions.size,
    });
    process.exit(0);
};
process.on("SIGTERM", () => shutdown("SIGTERM"));
process.on("SIGINT", () => shutdown("SIGINT"));

// ── Start ─────────────────────────────────────────────────────────────────────
const PORT = process.env.PORT || 3000;
app.listen(PORT, "0.0.0.0", () =>
    log("info", `auth-service listening`, { port: PORT }),
);
