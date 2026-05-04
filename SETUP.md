# Setup

## Fast Path

Prerequisites:

- Docker Engine: https://docs.docker.com/engine/install/
- Docker Compose plugin: https://docs.docker.com/compose/

From the repo root:

```bash
./setup.sh
```

That default path runs a fresh demo-ready install:

- bootstraps `.env` and runtime artifacts
- starts the local Docker Compose stack (`docker compose up -d --build`)
- resets the local database
- runs the headless installer with demo data
- saves generated install artifacts under `.blue-team-vm/runtime/install-artifacts/`

The demo bundle is expected to include the full repo runtime surface:

- `.env` and `.env.example`
- `compose.yaml`, `compose.app.yml`, and `compose.obs.yml`
- `config/`, `docker/`, `ops/`, `vendor/`, and `node_modules/`
- built frontend assets under `public/build/`

## Interactive Defaults

`./setup.sh` will ask for:

- admin email
- admin display name
- admin password
- destructive reset confirmation

## Non-Interactive

```bash
INSTALL_ADMIN_EMAIL=admin@example.com \
INSTALL_ADMIN_NICKNAME='System Administrator' \
INSTALL_ADMIN_PASSWORD='StrongPass123!45' \
INSTALL_ASSUME_YES=true \
./setup.sh
```

## Other Modes

`setup.sh` forwards to `install.sh` and keeps the same mode names:

```bash
./setup.sh demo dev
./setup.sh full dev
./setup.sh quick dev
./setup.sh deploy dev
./setup.sh verify dev
./setup.sh seed-admin dev
./setup.sh deploy production
./setup.sh ssl-switch <mode>
```

## SSL Modes

The repo-local stack keeps one HTTPS server block in nginx and switches certificate material by re-rendering `/etc/nginx/generated/ssl-mode.conf`. The runtime certificate directory lives under `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl`, so `./setup.sh ssl-switch <mode>` can update SSL material on a running stack without re-running the full installer.

### `self-signed`

Use for local development, demos, and any workstation-only stack where browser trust warnings are acceptable.

Prerequisites:

- no external CA account is required
- Docker must be able to bind `APP_PORT` and `APP_SSL_PORT`
- `SSL_CERT_DOMAIN` should match the hostname you browse locally; default: `localhost`

Runtime files:

- `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl/selfsigned.crt`
- `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl/selfsigned.key`
- `${BT_STATE_DIR:-.blue-team-vm}/runtime/rendered/nginx.ssl-mode.conf`

Initial setup:

```bash
SSL_MODE=self-signed \
SSL_CERT_DOMAIN=localhost \
./setup.sh
```

Hot switch on a running stack:

```bash
SSL_CERT_DOMAIN=localhost \
./setup.sh ssl-switch self-signed
```

### `cloudflare-origin`

Use when Cloudflare terminates public TLS and the local or lab nginx only needs an origin certificate/key pair.

Prerequisites:

- the hostname is proxied by Cloudflare
- `SSL_CERT_DOMAIN` is set to the certificate hostname
- provide either readable `SSL_CLOUDFLARE_ORIGIN_CERT_FILE` / `SSL_CLOUDFLARE_ORIGIN_KEY_FILE` source files or an executable `SSL_CLOUDFLARE_ORIGIN_GENERATE_HOOK`
- if you use source files, the PEM pair should already be downloaded from the Cloudflare dashboard
- if the generate hook talks to Cloudflare APIs, export `CF_Token` and `CF_Zone_ID`
- Cloudflare should be configured for `Full (strict)` on the public edge

Runtime files:

- `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl/cloudflare-origin/${SSL_CERT_DOMAIN}/cert.pem`
- `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl/cloudflare-origin/${SSL_CERT_DOMAIN}/key.pem`
- `${BT_STATE_DIR:-.blue-team-vm}/runtime/rendered/nginx.ssl-mode.conf`

Initial setup:

```bash
SSL_MODE=cloudflare-origin \
SSL_CERT_DOMAIN=jobs.example.com \
SSL_CLOUDFLARE_ORIGIN_CERT_FILE=/secure/path/origin-cert.pem \
SSL_CLOUDFLARE_ORIGIN_KEY_FILE=/secure/path/origin-key.pem \
./setup.sh
```

Hot switch on a running stack:

```bash
SSL_CERT_DOMAIN=jobs.example.com \
SSL_CLOUDFLARE_ORIGIN_CERT_FILE=/secure/path/origin-cert.pem \
SSL_CLOUDFLARE_ORIGIN_KEY_FILE=/secure/path/origin-key.pem \
./setup.sh ssl-switch cloudflare-origin
```

### `letsencrypt`

Use when the repo-local nginx should present the public certificate directly. The setup flow assumes DNS-01 and stores the resulting fullchain/private key inside the shared nginx runtime directory.

Prerequisites:

- `SSL_CERT_DOMAIN` resolves to the host serving the stack
- provide one of these certificate sources:
    - readable `SSL_LETSENCRYPT_CERT_PATH` / `SSL_LETSENCRYPT_KEY_PATH` files
    - an executable `SSL_LETSENCRYPT_GENERATE_HOOK`
    - a working built-in ACME flow selected by `SSL_ACME_CLIENT` (`acme.sh` by default, `certbot` supported)
- `SSL_LETSENCRYPT_CHALLENGE=dns-cloudflare` requires `CF_Token` and `CF_Zone_ID`
- `SSL_LETSENCRYPT_CHALLENGE=http-01` requires an existing `SSL_ACME_WEBROOT` directory that nginx can serve
- `SSL_ACME_CLIENT=certbot` also needs `SSL_ACME_EMAIL` (or `BT_CERTBOT_EMAIL`) and `SSL_CERTBOT_CREDENTIALS_FILE` for the Cloudflare DNS plugin
- the selected ACME client binary must be installed when you are not using pre-existing files or a generate hook

Runtime files:

- `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl/letsencrypt/${SSL_CERT_DOMAIN}/fullchain.pem`
- `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl/letsencrypt/${SSL_CERT_DOMAIN}/privkey.pem`
- `${BT_STATE_DIR:-.blue-team-vm}/runtime/rendered/nginx.ssl-mode.conf`

Initial setup:

```bash
SSL_MODE=letsencrypt \
SSL_CERT_DOMAIN=jobs.example.com \
SSL_ACME_CLIENT=acme.sh \
SSL_LETSENCRYPT_CHALLENGE=dns-cloudflare \
CF_Token=replace-me \
CF_Zone_ID=replace-me \
./setup.sh
```

Hot switch on a running stack:

```bash
SSL_CERT_DOMAIN=jobs.example.com \
SSL_ACME_CLIENT=acme.sh \
SSL_LETSENCRYPT_CHALLENGE=dns-cloudflare \
CF_Token=replace-me \
CF_Zone_ID=replace-me \
./setup.sh ssl-switch letsencrypt
```

Notes for `letsencrypt` mode:

- The local workflow is DNS-01 oriented so certificate issuance and renewal do not depend on re-running the full app setup.
- The SSL bootstrap path is expected to configure or refresh the auto-renew cron for the selected ACME client.
- If you prefer `certbot`, set `SSL_ACME_CLIENT=certbot` before the initial run or before `./setup.sh ssl-switch letsencrypt`.

### `custom`

Use when certificate material is issued outside this repo, for example ZeroSSL, and nginx should present that public certificate directly.

Prerequisites:

- `SSL_CERT_DOMAIN` is set to the certificate hostname.
- `SSL_CUSTOM_CERT_PATH` points to a PEM certificate or fullchain file.
- `SSL_CUSTOM_KEY_PATH` points to the matching private key.
- the certificate SAN includes the hostname users browse.

Runtime files:

- `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl/custom/${SSL_CERT_DOMAIN}/cert.pem`
- `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl/custom/${SSL_CERT_DOMAIN}/key.pem`
- `${BT_STATE_DIR:-.blue-team-vm}/runtime/rendered/nginx.ssl-mode.conf`

Initial setup:

```bash
SSL_MODE=custom \
SSL_CERT_DOMAIN=jb.mythic3011.com \
SSL_CUSTOM_CERT_PATH=ssl/fullchain.pem \
SSL_CUSTOM_KEY_PATH=ssl/private.key \
./setup.sh
```

Hot switch on a running stack:

```bash
SSL_CERT_DOMAIN=jb.mythic3011.com \
SSL_CUSTOM_CERT_PATH=ssl/fullchain.pem \
SSL_CUSTOM_KEY_PATH=ssl/private.key \
./setup.sh ssl-switch custom
```

## Switching Modes On A Running Stack

Use `./setup.sh ssl-switch <mode>` when the Docker stack is already up and you only need to replace SSL material. This is a runtime-only path: it should validate the target mode prerequisites, provision or copy the target certificate/key, update `${BT_STATE_DIR:-.blue-team-vm}/runtime/nginx-ssl` plus `${BT_STATE_DIR:-.blue-team-vm}/runtime/rendered/nginx.ssl-mode.conf`, and reload nginx in place when the container is already running. If nginx is not running, the runtime state should still be staged for the next start. It should not reset the database, rebuild demo data, rerun the headless installer, or require a full `docker compose down && docker compose up`.

## Notes

- `.env`, Docker Compose files, app code, vendor dependencies, and built frontend assets are expected to be present in this bundle.
- `bootstrap-env.sh` persists the detected or default shared app-plane network name into `.env` as `BT_APP_PLANE_NETWORK_NAME`. For the packaged demo bundle that default is `jobs-borads_app-plane`.
- The packaged demo bundle ships with `APP_PORT=80` and `APP_SSL_PORT=443`. If those host ports are already occupied, `setup.sh` / `install.sh` will stop and ask whether to reassign them unless `BT_AUTO_ASSIGN_PORTS=true` is set.
- CrowdSec defaults to local-only mode in this bundle. To use CrowdSec Console enrollment, set `CROWDSEC_DISABLE_ONLINE_API=false` and provide `CROWDSEC_ENROLL_KEY` before running setup.
- After setup, the install receipt is written to `.blue-team-vm/runtime/install.receipt.json`.
