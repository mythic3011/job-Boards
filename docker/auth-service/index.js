"use strict";

const express = require("express");
const bcrypt = require("bcrypt");
const crypto = require("crypto");
const cookieParser = require("cookie-parser");
const rateLimit = require("express-rate-limit");

const app = express();
app.set("trust proxy", 1); // trust nginx X-Forwarded-For
app.use(express.json());
app.use(cookieParser());

// serve built frontend assets if present
const path = require('path');
app.use(express.static(path.join(__dirname, 'public')));

// fallback for client-side routing (login page)
app.get(['/','/login'], (_req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// ── Logger ────────────────────────────────────────────────────────────────────
const log = (level, msg, meta = {}) => {
    console.log(
        JSON.stringify({
            ts: new Date().toISOString(),
            level,
            msg,
            ...meta,
        }),
    );
};

// ── Config ────────────────────────────────────────────────────────────────────
const USERS = {
    admin: process.env.MONITORING_PASSWORD_HASH || "",
};
const SESSION_TTL = 8 * 60 * 60 * 1000; // 8h
const SESSION_SECRET = process.env.SESSION_SECRET || "";

if (!USERS.admin) {
    log("warn", "MONITORING_PASSWORD_HASH not set — all logins will fail");
}
if (!SESSION_SECRET) {
    log("warn", "SESSION_SECRET not set — session HMAC disabled");
}

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
    if (!SESSION_SECRET) return token;
    const hmac = crypto
        .createHmac("sha256", SESSION_SECRET)
        .update(token)
        .digest("hex");
    return `${token}.${hmac}`;
};

const verifyToken = (signed) => {
    if (!signed) return null;
    if (!SESSION_SECRET) return signed; // unsigned mode
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

// ── Rate limiters ─────────────────────────────────────────────────────────────
// /verify: max 10 attempts per 15 min per IP
const verifyLimiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 10,
    standardHeaders: true,
    legacyHeaders: false,
    keyGenerator: (req) => req.headers["x-real-ip"] || req.ip,
    handler: (req, res) => {
        log("warn", "rate limit hit on /verify", {
            ip: req.headers["x-real-ip"] || req.ip,
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
    keyGenerator: (req) => req.headers["x-real-ip"] || req.ip,
    skipSuccessfulRequests: true, // only count failures
    handler: (req, res) => {
        log("warn", "rate limit hit on /check", {
            ip: req.headers["x-real-ip"] || req.ip,
        });
        res.status(429).end();
    },
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const getClientIP = (req) =>
    req.headers["x-real-ip"] ||
    req.headers["x-forwarded-for"]?.split(",")[0]?.trim() ||
    req.ip;

// ── Routes ────────────────────────────────────────────────────────────────────
app.get("/health", (_req, res) =>
    res.json({ status: "ok", sessions: sessions.size }),
);

app.post("/verify", verifyLimiter, async (req, res) => {
    const ip = getClientIP(req);
    const { username, password } = req.body || {};

    if (!username || !password) {
        log("warn", "verify: missing credentials", { ip });
        return res.status(400).json({ error: "Missing credentials" });
    }

    const hash = USERS[username];
    let valid = false;
    try {
        valid = hash ? await bcrypt.compare(password, hash) : false;
    } catch (err) {
        log("error", "bcrypt error", { ip, err: err.message });
        return res.status(500).json({ error: "Internal error" });
    }

    if (!valid) {
        log("warn", "verify: invalid credentials", { ip, username });
        // Delay to slow brute force even after rate limit resets
        await new Promise((r) => setTimeout(r, 500));
        return res.status(401).json({ error: "Invalid credentials" });
    }

    const rawToken = crypto.randomBytes(32).toString("hex");
    const signedToken = signToken(rawToken);

    sessions.set(rawToken, {
        username,
        expires: Date.now() + SESSION_TTL,
        ip,
        ua: req.headers["user-agent"] || "",
    });

    log("info", "verify: login success", {
        ip,
        username,
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
    const ip = getClientIP(req);
    const signedToken = req.cookies?.monitoring_session;
    const rawToken = verifyToken(signedToken);

    if (!rawToken) {
        return res.status(401).end();
    }

    const session = sessions.get(rawToken);

    if (!session) {
        return res.status(401).end();
    }

    if (session.expires < Date.now()) {
        sessions.delete(rawToken);
        log("info", "check: session expired", { ip });
        return res.status(401).end();
    }

    // IP binding — reject if IP changed
    if (session.ip !== ip) {
        sessions.delete(rawToken);
        log("warn", "check: IP mismatch — session invalidated", {
            expected: session.ip,
            got: ip,
            username: session.username,
        });
        return res.status(401).end();
    }

    // Refresh TTL on valid check
    session.expires = Date.now() + SESSION_TTL;
    res.status(200).end();
});

app.post("/logout", (req, res) => {
    const ip = getClientIP(req);
    const signedToken = req.cookies?.monitoring_session;
    const rawToken = verifyToken(signedToken);

    if (rawToken) {
        const session = sessions.get(rawToken);
        log("info", "logout", { ip, username: session?.username });
        sessions.delete(rawToken);
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
