"use strict";

const net = require("node:net");

const normalizeIp = (value) => {
    if (typeof value !== "string") {
        return "";
    }

    const trimmed = value.trim();
    if (trimmed === "") {
        return "";
    }

    if (trimmed.startsWith("::ffff:")) {
        return trimmed.slice(7);
    }

    return trimmed;
};

const parseTrustedProxyIps = (value) => {
    if (typeof value !== "string") {
        return [];
    }

    return value
        .split(",")
        .map((entry) => normalizeIp(entry))
        .filter((entry) => {
            if (entry === "") {
                return false;
            }

            if (net.isIP(entry) !== 0) {
                return true;
            }

            const [ip, prefix] = entry.split("/");
            if (!ip || !prefix || net.isIP(ip) !== 4) {
                return false;
            }

            const prefixNumber = Number(prefix);
            return Number.isInteger(prefixNumber) && prefixNumber >= 0 && prefixNumber <= 32;
        });
};

const ipv4ToInt = (ip) => {
    if (net.isIP(ip) !== 4) {
        return null;
    }

    const octets = ip.split(".");
    if (octets.length !== 4) {
        return null;
    }

    let value = 0;
    for (const octet of octets) {
        const parsed = Number(octet);
        if (!Number.isInteger(parsed) || parsed < 0 || parsed > 255) {
            return null;
        }
        value = (value << 8) + parsed;
    }

    return value >>> 0;
};

const parseIpv4Cidr = (entry) => {
    const [ip, prefixRaw] = entry.split("/");
    if (!ip || !prefixRaw || net.isIP(ip) !== 4) {
        return null;
    }

    const prefix = Number(prefixRaw);
    if (!Number.isInteger(prefix) || prefix < 0 || prefix > 32) {
        return null;
    }

    const ipInt = ipv4ToInt(ip);
    if (ipInt === null) {
        return null;
    }

    const mask = prefix === 0 ? 0 : ((0xffffffff << (32 - prefix)) >>> 0);
    const network = ipInt & mask;

    return { mask, network };
};

const createTrustedProxyMatcher = (trustedEntries = []) => {
    const exactIps = new Set();
    const cidrRanges = [];

    for (const rawEntry of trustedEntries) {
        const entry = normalizeIp(rawEntry);
        if (entry === "") {
            continue;
        }

        if (net.isIP(entry) !== 0) {
            exactIps.add(entry);
            continue;
        }

        const cidr = parseIpv4Cidr(entry);
        if (cidr) {
            cidrRanges.push(cidr);
        }
    }

    return (candidateIp) => {
        const normalized = normalizeIp(candidateIp);
        if (normalized === "") {
            return false;
        }

        if (exactIps.has(normalized)) {
            return true;
        }

        const candidateInt = ipv4ToInt(normalized);
        if (candidateInt === null) {
            return false;
        }

        return cidrRanges.some(
            ({ mask, network }) => (candidateInt & mask) === network,
        );
    };
};

const firstForwardedIp = (headerValue) => {
    if (typeof headerValue !== "string") {
        return "";
    }

    for (const part of headerValue.split(",")) {
        const normalized = normalizeIp(part);
        if (normalized !== "" && net.isIP(normalized) !== 0) {
            return normalized;
        }
    }

    return "";
};

const socketPeerIp = (request) =>
    normalizeIp(
        request?.socket?.remoteAddress ||
            request?.connection?.remoteAddress ||
            "",
    );

const requestIpFallback = (request) => {
    const requestIp = normalizeIp(request?.ip);
    if (requestIp !== "" && net.isIP(requestIp) !== 0) {
        return requestIp;
    }

    return "unknown";
};

const createClientIpResolver = ({ trustedProxyIps = [] } = {}) => {
    const isTrustedProxyPeer = createTrustedProxyMatcher(trustedProxyIps);

    return (request) => {
        const peerIp = socketPeerIp(request);

        if (peerIp === "") {
            return requestIpFallback(request);
        }

        if (!isTrustedProxyPeer(peerIp)) {
            return peerIp;
        }

        const realIp = normalizeIp(request?.headers?.["x-real-ip"]);
        if (realIp !== "" && net.isIP(realIp) !== 0) {
            return realIp;
        }

        const forwardedFor = firstForwardedIp(request?.headers?.["x-forwarded-for"]);
        if (forwardedFor !== "") {
            return forwardedFor;
        }

        return peerIp;
    };
};

const createCanonicalClientIpKeyGenerator = (resolveClientIp) => (request) =>
    resolveClientIp(request);

module.exports = {
    createCanonicalClientIpKeyGenerator,
    createClientIpResolver,
    createTrustedProxyMatcher,
    normalizeIp,
    parseTrustedProxyIps,
};
