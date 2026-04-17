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
        .filter((entry) => entry !== "" && net.isIP(entry) !== 0);
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

const createClientIpResolver = ({ trustedProxyIps = [] } = {}) => {
    const trustedPeers = new Set(trustedProxyIps.map((entry) => normalizeIp(entry)));

    return (request) => {
        const peerIp = socketPeerIp(request);

        if (peerIp === "" || !trustedPeers.has(peerIp)) {
            return peerIp;
        }

        const forwardedFor = firstForwardedIp(request?.headers?.["x-forwarded-for"]);
        if (forwardedFor !== "") {
            return forwardedFor;
        }

        const realIp = normalizeIp(request?.headers?.["x-real-ip"]);
        if (realIp !== "" && net.isIP(realIp) !== 0) {
            return realIp;
        }

        return peerIp;
    };
};

const createCanonicalClientIpKeyGenerator = (resolveClientIp) => (request) =>
    resolveClientIp(request);

module.exports = {
    createCanonicalClientIpKeyGenerator,
    createClientIpResolver,
    normalizeIp,
    parseTrustedProxyIps,
};
