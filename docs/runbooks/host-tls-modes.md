# Host TLS Modes

This runbook defines the host-layer TLS policy boundary. It does not issue certificates itself; it tells host bootstrap and UFW what the deployment expects.

## Modes

### `cloudflare-origin`

- Use when traffic is `client -> Cloudflare -> VPS -> project stack`.
- The VPS reverse proxy consumes an origin certificate, typically under `/etc/nginx/cert/<domain>/`.
- Public browser trust is provided by Cloudflare edge certificates, not by the origin certificate alone.
- Default host policy keeps `443/tcp` open and also keeps `80/tcp` open for HTTP-to-HTTPS redirect unless you explicitly disable it.

### `letsencrypt-http01`

- Use when the VPS itself presents the public certificate and Certbot uses the HTTP-01 challenge.
- `80/tcp` must remain reachable for issuance and renewal.
- `443/tcp` must remain reachable for live HTTPS traffic.
- Pair this mode with a host-level Certbot renewal path such as systemd timer/service. The application deploy workflow only consumes the issued files.
- Host bootstrap can manage the renewal contract through `ops/host/05-host-certbot-renewal.sh` when `BT_CERTBOT_DOMAIN` and `BT_CERTBOT_EMAIL` are set.

### `letsencrypt-dns01`

- Use when the VPS itself presents the public certificate and Certbot uses a DNS challenge.
- `443/tcp` must remain reachable for live HTTPS traffic.
- `80/tcp` is optional. Keep it open only if you want HTTP-to-HTTPS redirect behavior.
- To disable HTTP exposure, set `BT_ALLOW_HTTP_REDIRECT=0`.
- Host bootstrap can still manage the renewal timer/service contract for DNS-based renewal; only the challenge method changes outside this repository.

### `custom`

- Use when certificate issuance/renewal is handled by some other host process and the certificate files live outside the default layouts.
- Provide explicit cert/key paths through the deploy target environment.
- `443/tcp` remains required.
- `80/tcp` depends on whether you want HTTP redirect; control that through `BT_ALLOW_HTTP_REDIRECT`.

## Host bootstrap inputs

- `BT_HOST_TLS_MODE=cloudflare-origin|letsencrypt-http01|letsencrypt-dns01|custom`
- `BT_ALLOW_HTTP_REDIRECT=1` keeps `80/tcp` open for redirect or convenience
- `BT_ALLOW_HTTP_REDIRECT=0` closes the managed HTTP allowance unless the TLS mode itself requires `80/tcp` (`letsencrypt-http01`)
- `BT_CERTBOT_DOMAIN=<public-domain>` enables the managed Certbot renewal slice for Let's Encrypt modes
- `BT_CERTBOT_EMAIL=<ops-email>` records the operator account required by the renewal contract

## UFW behavior

- Managed host bootstrap always allows SSH according to the configured SSH policy.
- Managed host bootstrap always allows `443/tcp`.
- Managed host bootstrap allows `80/tcp` when either:
  - `BT_HOST_TLS_MODE=letsencrypt-http01`, or
  - `BT_ALLOW_HTTP_REDIRECT=1`

## Deployment boundary

- `ops/deploy/vps-deploy.sh` verifies that the certificate and key files already exist before reloading host nginx.
- Certificate issuance and renewal stay outside the application deploy workflow.
- Cloudflare edge mode and Certbot renewal policy should be documented together with the chosen deploy target so operators know whether the public certificate lives at the edge or on the VPS.
- Host bootstrap may manage the systemd renewal contract through `certbot-renew@.service` and `certbot-renew@.timer`, but it does not issue the initial certificate for you.
