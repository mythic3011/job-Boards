const test = require("node:test");
const assert = require("node:assert/strict");

const {
    createClientIpResolver,
    createCanonicalClientIpKeyGenerator,
} = require("../client-ip");

test("uses the raw peer IP when the immediate peer is not trusted", () => {
    const resolveClientIp = createClientIpResolver({
        trustedProxyIps: ["172.29.0.20"],
    });

    const request = {
        socket: { remoteAddress: "198.51.100.8" },
        headers: {
            "x-real-ip": "203.0.113.10",
            "x-forwarded-for": "203.0.113.10, 172.29.0.20",
        },
    };

    assert.equal(resolveClientIp(request), "198.51.100.8");
});

test("honors forwarded headers only when the immediate peer is trusted", () => {
    const resolveClientIp = createClientIpResolver({
        trustedProxyIps: ["172.29.0.20"],
    });

    const request = {
        socket: { remoteAddress: "172.29.0.20" },
        headers: {
            "x-real-ip": "203.0.113.10",
            "x-forwarded-for": "203.0.113.10, 172.29.0.20",
        },
    };

    assert.equal(resolveClientIp(request), "203.0.113.10");
});

test("uses the same canonical IP for limiter keys and session binding", () => {
    const resolveClientIp = createClientIpResolver({
        trustedProxyIps: ["172.29.0.20"],
    });
    const keyGenerator = createCanonicalClientIpKeyGenerator(resolveClientIp);

    const request = {
        socket: { remoteAddress: "::ffff:172.29.0.20" },
        headers: {
            "x-forwarded-for": "198.51.100.24, 172.29.0.20",
        },
    };

    assert.equal(resolveClientIp(request), "198.51.100.24");
    assert.equal(keyGenerator(request), "198.51.100.24");
});

test("falls back to request ip when the socket peer is unavailable", () => {
    const resolveClientIp = createClientIpResolver({
        trustedProxyIps: ["172.29.0.20"],
    });

    const request = {
        ip: "203.0.113.77",
        headers: {
            "x-forwarded-for": "198.51.100.24, 172.29.0.20",
        },
    };

    assert.equal(resolveClientIp(request), "203.0.113.77");
});
