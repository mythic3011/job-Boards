# Security Demo Runbook

This runbook keeps the course/demo evidence repeatable without mixing scanner logic into application deploy.

## Scope

- HTTPS evidence is collected from the public VPS-facing domain.
- ZAP evidence is collected by external Docker containers against the deployed target URL.
- The deploy workflow publishes the application; it does not itself issue certificates or run vulnerability scanners.

## HTTPS evidence

Use a publicly reachable reverse-proxy deploy such as:

- `ops/deploy/vps-deploy.sh jb.mythic3011.com <git-ref>`
- `ops/deploy/vps-deploy.sh from-env <git-ref>`

Required checks:

1. Why No Padlock
   - submit the public `https://...` URL to <https://www.whynopadlock.com/index.html>
   - capture the screenshot/output as evidence that the site is served over HTTPS
2. SSL Labs
   - submit the same public URL to <https://www.ssllabs.com/ssltest>
   - keep the final grade screenshot/result
   - minimum acceptable grade for the demo is `D`; `T` means the certificate is not trusted

Local artifact collector:

```bash
ops/demo/collect-security-demo-evidence.sh deployed https://jb.mythic3011.com demo-artifacts/security-demo
```

This writes:

- `demo-artifacts/security-demo/deployed/curl-headers.txt`
- `demo-artifacts/security-demo/deployed/openssl-certificate.txt`
- `demo-artifacts/security-demo/deployed/checklist.md`
- `demo-artifacts/security-demo/deployed/manifest.json`

Notes:

- If traffic is `client -> Cloudflare -> VPS -> project stack`, the public HTTPS screenshots reflect the Cloudflare edge certificate and TLS posture.
- The VPS reverse-proxy target must still consume a valid origin certificate path:
  - `TARGET_TLS_MODE=cloudflare-origin` for Cloudflare origin cert layouts
  - `TARGET_TLS_MODE=letsencrypt` when the host directly presents the public certificate
  - `TARGET_TLS_MODE=custom` when explicit cert/key paths are required

## ZAP before/after evidence

Use separate output folders for the pre-remediation and post-remediation revisions.

Example:

```bash
ops/demo/run-zap-baseline.sh before https://jb.mythic3011.com demo-artifacts/zap
ops/demo/run-zap-baseline.sh after https://jb.mythic3011.com demo-artifacts/zap
```

This writes:

- `demo-artifacts/zap/before/report.html`
- `demo-artifacts/zap/before/report.json`
- `demo-artifacts/zap/before/report.md`
- `demo-artifacts/zap/after/report.html`
- `demo-artifacts/zap/after/report.json`
- `demo-artifacts/zap/after/report.md`

Interpretation boundary:

- "before" should capture the baseline findings prior to the code/security revision you want to demonstrate.
- "after" should capture the remediated revision.
- Keep the two reports side-by-side; do not overwrite one with the other.
- The goal is to show that controllable major findings were removed or reduced. Uncontrollable platform/version findings should be explicitly called out rather than silently ignored.
- Pair each ZAP folder with a matching security-demo evidence folder so the screenshots, headers, certificate dump, and ZAP reports all point at the same deployed revision.

## UFW / front-door assumptions

- Public HTTPS evidence requires a reachable front door on `80/443`.
- Current host firewall/bootstrap scripts allow `80/tcp` and `443/tcp` alongside SSH.
- If the host uses Let's Encrypt HTTP-01, `80/tcp` must remain reachable for certificate issuance/renewal unless you move to a DNS-based flow.
- If Cloudflare fronts the site, keep the VPS origin certificate valid for the selected origin mode even though the public certificate is shown at the edge.
