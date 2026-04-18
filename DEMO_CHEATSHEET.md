# PolyU Demo Cheat Sheet — 2026-04-20

One-page reference for the 10-minute timed demo. Values for passwords live in `.env` (gitignored). Print this, tape it next to the demo machine.

## 0. Lab Network (reminder)

- Host (VMware): `10.22.x.x/24`
- Internal (WebServer/DB VM): `192.168.153.0/24`, GW `192.168.153.1`
- External (pfSense WAN): `158.132.209.0/24`, GW `158.132.209.28`
- Attacker (Kali): `158.132.209.W`

The app's `/install` and `/monitoring/*` are gated to `127.0.0.1` + `192.168.0.0/16`. `192.168.153.x` is inside that — you can reach them from the Web VM itself. The Kali attacker cannot.

## 1. Cold Boot → Demo Ready (run on Web/DB VM)

```bash
./install.sh demo dev
# Capture the two lines it prints:
#   "Demo company password: ..."
#   "Demo individual password: ..."
```

If containers already up:

```bash
./install.sh quick dev    # WIPES DATA, re-seeds
```

Re-seed demo users only without wiping:

```bash
docker exec jobs-boards-laravel.test php artisan db:seed --class=DemoDataSeeder
```

## 2. URLs

| What | URL |
|------|-----|
| App home | `https://<web-vm-ip>/` |
| Login | `https://<web-vm-ip>/login` |
| Admin dashboard | `https://<web-vm-ip>/admin` |
| Install wizard (local only) | `https://<web-vm-ip>/install` |
| Monitoring login | `https://<web-vm-ip>/monitoring/login` |
| Grafana | `https://<web-vm-ip>/monitoring/grafana` |
| Prometheus | `https://<web-vm-ip>/monitoring/prometheus` |

## 3. Credentials (look up in `.env` on the VM)

| Role | Username / env key | Password source |
|------|--------------------|-----------------|
| Admin (app) | created by `./install.sh demo` or `setupAdmin` | wizard / CLI prompt |
| Admin 2FA | — | TOTP set at first admin login |
| Demo company user | `brightpath_hr` | printed by `DemoDataSeeder` |
| Demo individual user | `alex_morgan` | printed by `DemoDataSeeder` |
| Monitoring UI | `MONITORING_ADMIN_USERNAME` | `MONITORING_PASSWORD` |
| Grafana admin | `admin` | `GRAFANA_PASSWORD` |
| Prometheus basic-auth | — | `PROMETHEUS_PASSWORD` |
| PostgreSQL | `DB_USERNAME` | `DB_PASSWORD` |
| Redis | — | `REDIS_PASSWORD` |
| pfSense web UI | `admin` | set in pfSense VM (not in repo) |

Quick dump: `grep -E "^(MONITORING_|GRAFANA_|PROMETHEUS_|DB_|REDIS_)" .env`

## 4. Attack → Response Playbook (10 min)

| Teacher's attack | What already protects | If it bites, do this |
|------------------|-----------------------|----------------------|
| `hping3` SYN flood | pfSense state/rate limits | Set pfSense Firewall → Advanced → State Table limits; add floating rule `max-src-conn 50 max-src-conn-rate 20/5` |
| `SlowHTTPTest` | nginx tight timeouts (5s body/header), `limit_conn per_ip_conn 10`, `reset_timedout_connection on` | Temporarily lower `limit_conn` to 5; `docker compose restart nginx` |
| `THC-SSL-DOS` | TLS session tickets off, short keepalive | Block source IP at pfSense or: `iptables -A INPUT -s <kali-ip> -j DROP` on Web VM |
| `sqlmap` | Laravel Eloquent = parameterized; `BlockBadUserAgent` blocks `sqlmap`, `hydra`, `nikto`, `python-requests` UAs | Show it getting 403 in nginx logs |
| SQLi manual | Eloquent + validation | Show rejected input in Laravel log |
| XSS | CSP header (nonce-based), Blade `{{ }}` auto-escape | Show CSP block in browser console |
| XSRF | Laravel `VerifyCsrfToken` middleware | Show 419 response on missing token |
| Bot/UA probing | `BlockBadUserAgent`, `HoneypotProtection`, CrowdSec Lua bouncer | Show access log entries tagged `blocked.ua` |

Kill switch (ask teacher to stop attack): costs 10% of demo marks — use only if rundown is going to time out.

## 5. Live-Mitigation Commands (have these in history)

```bash
# Tail nginx access/error (split shells)
docker logs -f nginx-bt 2>&1 | grep -E "429|403|blocked"
docker exec jobs-boards-laravel.test tail -f storage/logs/laravel.log

# Drop a bad IP fast (Web VM)
sudo iptables -A INPUT -s 158.132.209.W -j DROP
# Undo
sudo iptables -D INPUT -s 158.132.209.W -j DROP

# Restart nginx after tweaking limits
docker compose restart nginx

# Check CrowdSec decisions
docker exec crowdsec cscli decisions list

# Verify setup_completed so /install redirect works right
docker exec jobs-boards-laravel.test php artisan tinker --execute="echo \App\Models\Setting::get('setup_completed');"
```

## 6. Security-Demo (Not Timed)

**HTTPS — whynopadlock / ssllabs**: cert is self-signed → expect `T` grade on SSL Labs. Explain it's a lab self-signed cert; cipher/protocol quality is A-tier. Fix for VPS deploy = Let's Encrypt.

**ZAP scan**: run before and after; show major vulns resolved. Baseline report file should sit in `storage/security-reports/`. If missing, run:

```bash
# From Kali
zaproxy -cmd -quickurl https://<web-vm-ip> -quickout /tmp/zap-report.html
```

**Firewall (pfSense)**: show Services → Snort/Suricata rules, Firewall → Rules → WAN with state limits, and the `192.168.153.0/24` → WAN NAT rule.

**Server hardening**: point to `docker/nginx/nginx.conf` — our mod_evasive equivalent is nginx `limit_req_zone` + `limit_conn_zone` + `reset_timedout_connection on` + CrowdSec Lua bouncer. Show `limit_req_status 429` responses hitting during the demo.

## 7. Rollback / Recovery If It All Breaks

```bash
# Nuclear: fresh migrate + re-seed (loses all demo state)
./install.sh quick dev

# Reset admin 2FA if locked out
docker exec jobs-boards-laravel.test php artisan tinker --execute="\App\Models\User::where('login_id','<admin>')->update(['two_factor_secret'=>null]);"

# Unban your own IP from CrowdSec
docker exec crowdsec cscli decisions delete --ip <your-ip>
```

## 8. Do NOT Forget

- [ ] `.env` has non-blank `MONITORING_PASSWORD`, `GRAFANA_PASSWORD`, `PROMETHEUS_PASSWORD`, `SESSION_SECRET`, `DB_PASSWORD`
- [ ] 2FA TOTP enrolled on admin account before demo
- [ ] Demo seed ran — `brightpath_hr` + `alex_morgan` exist
- [ ] pfSense WAN rules saved
- [ ] Two terminal tabs pre-opened: nginx log + laravel log
- [ ] Browser bookmarks: `/`, `/login`, `/admin`, `/monitoring/grafana`
- [ ] ZAP baseline report screenshot ready
