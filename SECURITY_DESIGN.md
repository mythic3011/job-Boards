# Security Design & Trade-offs

Job board (Laravel 12 + PostgreSQL + Nginx/OpenResty). Deployed to PolyU lab scenario (pfSense + Web/DB VM) and, in production, a VPS fronted by Cloudflare.

## 1. Threat Model

Actors we defended against, ranked by demo priority:

| Actor | Goal | Primary vectors |
|-------|------|-----------------|
| Script kiddie w/ Kali | Take the app offline | hping3 SYN flood, SlowHTTPTest, THC-SSL-DOS |
| Automated scanner | Enumerate & exploit | sqlmap, nikto, nmap, directory brute-force |
| Manual attacker | Account takeover / data theft | SQLi, XSS, CSRF, credential stuffing, 2FA bypass |
| Insider / compromised user | Privilege escalation | IDOR, forced browsing, session fixation |

Out of scope for this milestone: L3 DDoS from botnets (handled upstream at pfSense / Cloudflare), supply-chain compromise, physical access to VMs.

## 2. Defense Architecture

```
Internet ──► pfSense (L3/L4 rate limit, NAT)
              │
              ▼
        Nginx / OpenResty ──► [Lua: CrowdSec bouncer]
         │ • HTTPS (self-signed in lab / CF Origin in prod)
         │ • limit_req + limit_conn
         │ • tight timeouts (5s client header/body)
         │ • security headers (CSP-nonce, HSTS, X-Frame-Options)
         │ • /install + /monitoring gated to 192.168.0.0/16
         ▼
        PHP-FPM (Laravel)
         │ • HoneypotProtection + risk-based AntiBot middleware
         │ • Fortify (rate-limited login, 2FA, password policy)
         │ • RequireAdminTwoFactor, HideAdminRoutes
         │ • Eloquent ORM (parameterized queries)
         │ • CSRF middleware, nonce-based CSP
         │ • AuditLogger → PostgreSQL
         ▼
        PostgreSQL (private network, password auth, UUID PKs)
```

Defense-in-depth: each attack must beat **at least two** independent layers to succeed.

## 3. Attack → Defense Mapping

| Attack | Mitigated by (code reference) |
|--------|-------------------------------|
| SYN flood (hping3) | pfSense WAN state limits; nginx `limit_conn per_ip_conn 10` |
| Slowloris / SlowHTTPTest | `client_header_timeout 5`, `client_body_timeout 5`, `reset_timedout_connection on` (docker/nginx/nginx.conf:311-317) |
| THC-SSL-DOS | `ssl_session_tickets off`, `keepalive_timeout 15`, `limit_conn 10` |
| SQLi | Eloquent parameterization + validation rules + CrowdSec/AppSec signatures |
| Stored XSS | Blade auto-escape; nonce-based `Content-Security-Policy` (app/Http/Middleware/SecurityHeaders.php) |
| CSRF | `VerifyCsrfToken` on every state-changing route |
| Credential stuffing | Fortify rate-limit 5/min per `login_id+IP` (FortifyServiceProvider); `AccountLockout` after 5 failures × 30 min |
| 2FA bypass on admin | `RequireAdminTwoFactor` middleware gates all `/admin/*` |
| Admin route enumeration | `HideAdminRoutes` returns 404 to non-admins |
| Install-wizard abuse post-setup | `EnsureSetupCompleted` + nginx `allow 192.168.0.0/16; deny all;` on `/install` |
| IDOR on application/job | Policy-gated `$this->authorize(...)` in controllers; public identifiers use `idcode` (UUID), not sequential PKs |
| CV file poisoning | `ValidCvFile` rule: MIME sniff, size cap, SHA-256 on write; stored under `storage/app/private/` |
| Bot probes | Nginx/CrowdSec rate+pattern detection, `HoneypotProtection`, anti-bot fingerprint telemetry |

## 4. Design Trade-offs (Alternatives Considered)

### 4.1 TLS termination

| Chose | Rejected | Why |
|-------|----------|-----|
| **Self-signed cert in lab**, Cloudflare Origin Certificate in production | Let's Encrypt on origin | Lab has no public DNS → LE HTTP-01 impossible. CF Origin cert is 15-year, trusted by CF edge (browsers see CF's trusted cert), eliminates renewal cron. |

**Cost**: SSL Labs grades the lab cert `T` (untrusted). Accepted because rubric requires "`D` or above" and grade is explained on demo day. Cipher/protocol config is A-tier independent of cert trust.

### 4.2 Anti-DDoS / Rate Limiting

| Chose | Rejected | Why |
|-------|----------|-----|
| **Nginx `limit_req_zone` + `limit_conn_zone` + CrowdSec Lua bouncer** | Apache + `mod_evasive2` | Apache/mod_evasive has no equivalent for Slowloris-style attacks without upstream proxy; OpenResty Lua lets us plug CrowdSec's L7 behaviour-based IP banning. |
| | `fail2ban` via iptables | fail2ban tails logs — reactive with log-rotation lag. CrowdSec decisions propagate per-request via shared dict. |

**Cost**: OpenResty + Lua adds operational complexity; degrades to pass-through if the bouncer key is missing (explicit `X-CrowdSec-Mode: degraded` header for debugging).

### 4.3 Authentication identifier

| Chose | Rejected | Why |
|-------|----------|-----|
| **`login_id` (arbitrary handle)** | `email` | Email enumeration is a disclosure vector; `login_id` lets legitimate users share an email (e.g., shared recruiting mailbox) and decouples identity from a mutable contact attribute. |

**Cost**: Password-reset flow needs an email *separately* (`SendPasswordResetLinkWithTwoFactor`), adding one indirection.

### 4.4 Admin 2FA enforcement

| Chose | Rejected | Why |
|-------|----------|-----|
| **Mandatory TOTP for every admin account via middleware** | Optional 2FA / SMS | Admin compromise is catastrophic (can edit any application). TOTP is free, offline, and phishing-resistant enough. SMS is SIM-swap vulnerable. |

**Cost**: Admin lockout if device is lost — partially mitigated by recovery codes, but still a support burden. For demo, documented reset via `tinker` in cheat sheet.

### 4.5 Public identifiers

| Chose | Rejected | Why |
|-------|----------|-----|
| **UUID `idcode` in URLs (e.g., `/jobs/job_<uuid>`)** | Auto-increment integer in URLs | Prevents ID enumeration and competitive intelligence leakage ("how many applications have you received?"). DB-internal `id` still integer-like (PostgreSQL `gen_random_uuid`) for query-planner friendliness. |

**Cost**: URLs are longer and unmemorable; scoping queries by idcode requires the `byIdcode` scope. Accepted.

### 4.6 Rate-limit keying

| Chose | Rejected | Why |
|-------|----------|-----|
| **Per `(login_id, IP)` tuple for login** | Per IP alone | IP-only is defeated by distributed stuffing. `login_id`-only is defeated by proxy rotation. Tuple forces both axes to scale. |

**Cost**: Shared-NAT users (corporate networks, schools) can collide — accepted, with 5/min still generous for real humans.

### 4.7 Bot detection

| Chose | Rejected | Why |
|-------|----------|-----|
| **Risk-based anti-bot signals (rate, pattern, honeypot, telemetry)** | CAPTCHA on every form | CAPTCHA friction → abandonment; reCAPTCHA sends user data to Google (privacy concern). Our stack is server-side only. |

**Cost**: Requires tuning thresholds and scenario rules across Nginx/CrowdSec/Laravel to avoid false positives while maintaining detection quality.

### 4.8 Monitoring plane auth

| Chose | Rejected | Why |
|-------|----------|-----|
| **Dedicated Node.js auth-service w/ HMAC session tokens** | Nginx basic-auth (`htpasswd`) | Basic-auth sends credentials in every request; no revocation; no audit trail. Auth-service issues HMAC-signed session tokens, has logout, logs every verify. |
| | Laravel session reuse | Keeps monitoring auth isolated from app session — app compromise doesn't auto-grant Grafana access. |

**Cost**: One extra container (auth-service); operators manage two credential stores (app admin vs monitoring).

### 4.9 IP allowlist

| Chose | Rejected | Why |
|-------|----------|-----|
| **Static CIDR allowlist for `/install` + `/monitoring` (127.0.0.1 + 192.168.0.0/16)** | Env-driven toggle | Simpler mental model for demo. Known limitation for VPS deploy — documented follow-up to make it `DEPLOYMENT_PROFILE`-aware. |

**Cost**: VPS deploy will lock out remote ops until toggled manually. Acknowledged debt.

### 4.10 Timeout tightening (5s vs default 60s)

| Chose | Rejected | Why |
|-------|----------|-----|
| **`client_body_timeout 5`, `client_header_timeout 5`** | Defaults (60s) | 60s gives Slowloris 12× the budget to hold a worker. 5s is well beyond real-world network latency (P99 < 1s even on 4G). |

**Cost**: Very slow mobile networks on large uploads may retry. `/upload` location explicitly raises `client_max_body_size` but not timeouts — future refinement.

## 5. Untaught Techniques Self-Learned

Not in the course material, researched and integrated:

- **CrowdSec + OpenResty Lua bouncer** — alternative to `fail2ban`, decision propagation via shared dict. Required writing a graceful-degrade wrapper (`bt_crowdsec_degrade`) so a missing key doesn't block every request.
- **Nonce-based CSP** — generated per-request by `SecurityHeaders`; passed to Blade via a view composer so inline scripts remain possible without `'unsafe-inline'`.
- **UUIDv4 via PostgreSQL `gen_random_uuid()`** — offloads generation to DB to avoid collisions across parallel workers.
- **HMAC session tokens in auth-service** (ref: IETF draft-ietf-oauth-signed-http-request) — chose HMAC over JWT for no-crypto-agility surprises.

## 6. Scope Discipline & Deferred Work

Explicitly time-boxed out of this milestone:

| Deferred | Why | When |
|----------|-----|------|
| Unified `DEPLOYMENT_PROFILE` env (`lab`/`vps`/`dev`) | Would require refactoring install.sh + nginx config templating; not demo-critical | Post-demo sprint |
| Let's Encrypt / CF Origin cert automation | Demo is on lab IP; VPS path separate | Deploy phase |
| WAF rule tuning (ModSecurity OWASP CRS) | CrowdSec + app middleware cover demo attacks; CRS tuning is a multi-day effort | Future hardening |
| Brotli compression | Gzip suffices for demo | Future perf pass |
| Backup / DR | Single-VM demo, no HA needed | Production only |

## 7. Verification Strategy

- **Feature tests** (`tests/Feature/`) exercise the happy path + authorization negatives.
- **Unit tests** (`tests/Unit/Services/`) cover service-layer business rules (application-state transitions, CV validation).
- **Contract test** (`UiEmptyStateContractTest`) locks the empty-state component API across callers.
- **Security self-scan**: OWASP ZAP baseline in Kali, before-and-after screenshots archived in `storage/security-reports/`.

## 8. References

- OWASP Top 10 (2021) — mapping in Section 3
- PHP-Security-Cheatsheet (OWASP)
- Mozilla Observatory — CSP / HSTS guidance
- CrowdSec docs — Lua bouncer integration
- Laravel Fortify source — rate-limit & 2FA baseline
