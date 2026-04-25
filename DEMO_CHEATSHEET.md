# Demo Cheat Sheet — 2026-04-20

**Lab:** Web/DB VM `192.168.153.x` · pfSense WAN `158.132.209.x` · Kali attacker on WAN.

## Boot
```bash
unzip jobs-borads-demo.zip && cd jobs-borads
./setup.sh
# or direct local flow:
docker compose up -d --build
```

## URLs
Default packaged bundle: `https://<web-vm-ip>`

If setup had to reassign host ports, check `.env`:
```bash
grep -E '^(APP_PORT|APP_SSL_PORT)=' .env
```

Paths:
`/` · `/login` · `/admin` · `/monitoring/login` · `/monitoring/grafana` · `/install` (local only)

## Creds
Infrastructure secrets stay in `.env`:
`grep -E '^(MONITORING_|DB_|REDIS_)' .env`
- Primary monitoring login: `MONITORING_ADMIN_USERNAME` + `MONITORING_PASSWORD`
- `GRAFANA_PASSWORD` and `PROMETHEUS_PASSWORD` are legacy compatibility aliases, not the normal operator contract
- Demo installer outputs are saved under `.blue-team-vm/runtime/install-artifacts/`
- Admin + demo-user bootstrap details come from the latest `headless-install-*.json`

## Attack → Response
| Attack | Covered by | Panic button |
|---|---|---|
| `hping3` SYN flood | pfSense state limits | pfSense → Firewall → WAN floating rule: `max-src-conn 50 max-src-conn-rate 20/5` |
| `SlowHTTPTest` | nginx `client_*_timeout 5s`, `reset_timedout_connection`, `limit_conn 10` | `docker compose restart nginx` |
| `THC-SSL-DOS` | `ssl_session_tickets off`, `keepalive_timeout 15` | `sudo iptables -A INPUT -s <kali-ip> -j DROP` |
| `sqlmap` / SQLi | Eloquent params + `BlockBadUserAgent` (blocks sqlmap UA) | show 403 in nginx log |
| XSS | CSP nonce + Blade escape | show browser console block |
| XSRF | `VerifyCsrfToken` | show 419 response |

Kill-switch (ask teacher to stop) = -10% marks. Use only if timing out.

## Live tails
```bash
docker logs -f jobs-boards-nginx 2>&1 | grep -E "429|403|blocked"
docker exec jobs-boards-laravel.test tail -f storage/logs/laravel.log
docker exec jobs-boards-crowdsec cscli decisions list
```

## Security Demo (untimed)
- **whynopadlock / SSL Labs:** self-signed → expect `T`. Explain: lab cert; VPS uses CF Origin cert.
- **ZAP:** show before/after baseline reports.
- **Firewall:** show pfSense WAN rules + state table limits.
- **Hardening (mod_evasive equivalent):** nginx `limit_req_zone` + `limit_conn_zone` + tight timeouts + CrowdSec Lua bouncer.

## Pre-flight
- [ ] `.env` has non-blank `MONITORING_ADMIN_USERNAME`, `MONITORING_PASSWORD`
- [ ] `.blue-team-vm/runtime/obs.generated.env` exists (compose init/bootstrap artifact)
- [ ] `.env` has `BT_APP_PLANE_NETWORK_NAME=jobs-borads_app-plane` unless you intentionally overrode it (advanced/split-plane debug)
- [ ] Admin 2FA enrolled
- [ ] Seeder ran (demo users exist)
- [ ] 2 terminals: nginx log + laravel log
