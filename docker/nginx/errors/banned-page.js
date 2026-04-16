(function () {
    function fallbackFingerprint() {
        return {
            fp: "0".repeat(64),
            headless: true,
            canvas_ok: false,
            webgl_vendor: "unavailable",
        };
    }

    async function collectFingerprint() {
        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d");
        ctx.fillText("fp-probe", 10, 10);
        const canvasData = canvas.toDataURL();
        const canvasOk = canvasData.length > 100;

        let webglVendor = "none";
        let headless = !canvasOk;
        const gl = canvas.getContext("webgl");
        if (gl) {
            const ext = gl.getExtension("WEBGL_debug_renderer_info");
            if (ext) {
                webglVendor = gl.getParameter(ext.UNMASKED_VENDOR_WEBGL);
            } else {
                headless = true;
            }
        } else {
            headless = true;
        }

        const entropy = [
            navigator.hardwareConcurrency,
            navigator.deviceMemory || 0,
            screen.colorDepth,
            screen.width,
            screen.height,
            navigator.platform,
        ].join("|");

        const raw = canvasData + webglVendor + entropy;
        const buf = await crypto.subtle.digest(
            "SHA-256",
            new TextEncoder().encode(raw),
        );

        return {
            fp: Array.from(new Uint8Array(buf))
                .map((byte) => byte.toString(16).padStart(2, "0"))
                .join(""),
            headless,
            canvas_ok: canvasOk,
            webgl_vendor: webglVendor,
        };
    }

    const fingerprintPromise = collectFingerprint().catch(function () {
        return fallbackFingerprint();
    });

    function buildProbePayload(fingerprint) {
        return {
            fp: fingerprint.fp,
            headless: Boolean(fingerprint.headless),
            canvas_ok: Boolean(fingerprint.canvas_ok),
            webgl_vendor: fingerprint.webgl_vendor ?? null,
            ts: Date.now(),
        };
    }

    function postProbe(signal) {
        return fingerprintPromise.then(function (fingerprint) {
            const payload = buildProbePayload(fingerprint);
            const probeUrl = `/api/bot/fp-log?${new URLSearchParams({ probe: "banned_page", signal })}`;

            if (signal === "mousemove" && navigator.sendBeacon) {
                navigator.sendBeacon(
                    probeUrl,
                    new Blob([JSON.stringify(payload)], {
                        type: "application/json",
                    }),
                );
                return;
            }

            fetch(probeUrl, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
                keepalive: true,
            }).catch(function () {});
        });
    }

    postProbe("page_load");

    if (crypto && crypto.subtle) {
        const seed = new TextEncoder().encode(Math.random().toString(36));
        crypto.subtle
            .importKey("raw", seed, { name: "PBKDF2" }, false, [
                "deriveBits",
            ])
            .then(function (key) {
                return crypto.subtle.deriveBits(
                    {
                        name: "PBKDF2",
                        salt: new TextEncoder().encode(location.hostname),
                        iterations: 200000,
                        hash: "SHA-256",
                    },
                    key,
                    256,
                );
            })
            .then(function (bits) {
                void bits;
            });

        setTimeout(function () {
            const seed2 = new TextEncoder().encode(Math.random().toString(36));
            crypto.subtle
                .importKey("raw", seed2, { name: "PBKDF2" }, false, [
                    "deriveBits",
                ])
                .then(function (key) {
                    return crypto.subtle.deriveBits(
                        {
                            name: "PBKDF2",
                            salt: new TextEncoder().encode(location.hostname + "2"),
                            iterations: 200000,
                            hash: "SHA-256",
                        },
                        key,
                        256,
                    );
                })
                .then(function (bits) {
                    void bits;
                });
        }, 8000);
    }

    let beaconFired = false;
    document.addEventListener(
        "mousemove",
        function () {
            if (!beaconFired) {
                beaconFired = true;
                postProbe("mousemove");
            }
        },
        { once: true },
    );

    let scrollFired = false;
    window.addEventListener("scroll", function () {
        if (
            !scrollFired &&
            window.innerHeight + window.scrollY >= document.body.offsetHeight - 10
        ) {
            scrollFired = true;
            new Image().src = "/jobs?page=" + Math.floor(Math.random() * 9999);
        }
    });
})();
