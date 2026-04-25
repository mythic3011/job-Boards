# Application Environment Configuration Cleanup

Status: inventory/design only. No runtime behavior change in this pass.

## PR1 Output Boundary

This document is the PR1 output. It is intentionally limited to:

- application env inventory
- semantic role grouping
- duplicate / overlap analysis
- keep/default/derive/merge/remove/move-to-CLI classification
- `.env.example` consumer-proof and owner-proof rule
- generated-path derivation targets
- migration risks and unresolved contract questions

This document is not a bootstrap implementation spec, not a Compose rewrite,
and not a runtime migration PR.

This document intentionally narrows the ticket into the first implementation
boundary. The required order is:

1. Clean application environment configuration first.
2. Group variables by semantic role.
3. Remove duplicated semantic roles from the operator-facing surface.
4. Derive generated/internal paths automatically.
5. Add one bootstrap/init layer that validates and generates runtime config
   before services start.
6. Refactor wider deployment flow only after the application config model is
   stable.

Out of scope for this pass:

- rewriting Compose services/templates
- changing deployment behavior
- replacing the install flow
- deleting currently consumed variables before compatibility shims exist
- making `demo` destructive

## Non-goals For PR1

PR1 must not:

- edit `.env.example`
- change install command behavior
- change generated secret behavior
- change Compose interpolation behavior
- change TLS mode selection
- change demo/reset semantics

PR1 may add documentation and non-invasive inventory checks only.

## PR1 Check Boundary

Non-invasive inventory checks may affect CI only. They must not alter install,
runtime, Compose, TLS, secret-generation, or demo/reset behavior.

PR layering:

- PR1: inventory, semantic role grouping, consumer/owner proof rule, and risks.
- PR2: application env semantic cleanup, canonical mapping, and compatibility
  contract.
- PR3: bootstrap/CLI/runtime bridge only after the application config model is
  stable.

Source of truth for the staged cleanup:

- clean application-facing environment configuration first
- merge variables that express the same semantic role
- derive generated file paths from one state root
- keep internal service wiring out of normal operator configuration
- keep `demo` deployment separate from destructive `reset-demo`
- normal services must not start until bootstrap/init succeeds

## Target Operator Shape After Compatibility Exists

This is a target contract direction for later behavior-changing PRs, not a PR1
deliverable.

Normal lab/demo deployment should eventually be close to:

```dotenv
APP_DOMAIN=jobs-board.lab
ADMIN_EMAIL=admin@example.com
```

If lab/demo can run safely with a mode default such as `jobs-board.lab`, then
`APP_DOMAIN` should also be defaulted by mode and omitted from the required
operator path. If DNS, certificates, or the demo target require an operator
owned domain, keep `APP_DOMAIN` operator-facing. The implementation PR must
make this decision explicitly instead of assuming either direction.

`STATE_DIR` must not be required normal operator configuration. For lab/demo,
it should default to `.blue-team-vm` automatically. It is an advanced override
only.

Production must have a separate contract/profile. Production may require a real
domain, external TLS, external secret injection, and a monitoring access policy,
but those requirements must not leak back into the normal lab/demo
`.env.example`.

Production may require additional explicit secrets or secret-file inputs, but
those should be limited to values the operator actually owns. Generated paths,
generated secret file paths, container ports, compose network names, rendered
config paths, and internal service URLs should be derived.

`APP_DOMAIN` is the proposed canonical public host identity, not the full
public URL contract. `APP_URL` should be derived from `APP_DOMAIN` plus
TLS/profile defaults unless the deployment profile explicitly supports a
non-standard scheme, port, path base, or external asset host.

`STATE_DIR` is the proposed canonical state root for the bootstrap layer.
During migration it can map to the current `BT_STATE_DIR`; downstream paths
should be derived from it. It should default to `${ROOT_DIR}/.blue-team-vm`
and stay out of the normal lab/demo operator template. Relative overrides must
be resolved against `ROOT_DIR`, not the caller's current working directory.

`APP_DOMAIN` and `STATE_DIR` are proposed canonical names for later PRs. This
inventory does not add them as new required runtime variables yet. The next
implementation step must either prove they are genuinely external operator
inputs or keep compatibility with current names while shrinking `.env.example`.
Generated secrets such as `APP_KEY`, `DB_PASSWORD`, `MONITORING_PASSWORD`, and
`CANONICAL_AUDIT_AUTH_SERVICE_SECRET` should not remain in the normal
operator-facing template just because bootstrap can leave them blank. If
bootstrap can generate them for lab/demo, it should materialize them into
derived runtime/secret files or compatibility env for existing consumers. In
the PR2 semantic contract these entries should classify as bootstrap/profile
owned generated or injected secrets, not `operator + keep-normal`.

## Semantic Role Grouping

| Semantic role | Current variables | Canonical direction | Notes |
|---|---|---|---|
| Deployment mode | `SETUP_MODE`, positional install action, `ENV_MODE`, `INSTALL_ENV_MODE`, `BOOTSTRAP_MODE`, `DEPLOY_BOOTSTRAP_MODE` | move to CLI | `./install.sh lab|demo|production`; do not use hidden `DEPLOY_MODE=...`. |
| Setup/deploy/app mode naming | `APP_ENV`, `ENV_MODE`, `INSTALL_ENV_MODE`, install action names, deploy bootstrap mode names | merge/default | App runtime mode, deployment mode, and destructive setup action are separate concepts and should not share ambiguous names. |
| Public app host identity | `APP_URL`, `ASSET_URL`, `SSL_CERT_DOMAIN`, `SSL_CERT_ALT_NAMES`, `SSL_SELF_SIGNED_ALT_NAMES`, `TARGET_DOMAIN`, `TARGET_SUBDOMAIN`, `TARGET_ROOT_DOMAIN`, `DEPLOY_DOMAIN`, nginx `server_name` template value | merge | Canonical host input should be `APP_DOMAIN`; derive URL, cert CN/SAN defaults, and server names. Keep full URL/profile support for non-standard scheme, port, path base, or asset host. |
| Public HTTP/HTTPS host bindings | `APP_PORT`, `APP_SSL_PORT`, `TARGET_APP_PORT`, `TARGET_APP_SSL_PORT`, `DEPLOY_APP_PORT`, `DEPLOY_APP_SSL_PORT`, `DEPLOY_NGINX_PROXY_PASS` | default/derive | Internal to local/host topology. Normal operator should not need these for lab/demo. |
| Internal service ports | `PORT`, `VITE_PORT`, `DB_PORT`, `REDIS_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT` | default/derive | Container-side ports are implementation detail; host-forwarded dev ports are local override only. |
| Application runtime mode | `APP_ENV`, `APP_DEBUG`, `LOG_LEVEL` | default from CLI mode | Production CLI sets production-safe values; lab/demo default locally. |
| Laravel application secret | `APP_KEY` | derive/generate | Required secret value, not operator path. Generate only for lab/demo or first-run flows explicitly allowed by the mode contract. Production must validate or inject and must never rotate implicitly. |
| Laravel encryption fallback keys | `APP_PREVIOUS_KEYS` | rotation-only | Managed only by an explicit key-rotation flow; never generated during normal bootstrap. |
| Admin bootstrap identity | `ADMIN_EMAIL` proposed, `INSTALL_ADMIN_EMAIL`, `JB_INSTALL_ADMIN_EMAIL` | merge | One operator-facing admin email concept. Existing install-specific names should become CLI/headless compatibility only. |
| Admin bootstrap password | `INSTALL_ADMIN_PASSWORD`, `INSTALL_ADMIN_PASSWORD_FILE`, `JB_INSTALL_ADMIN_PASSWORD` | keep secret input | Prefer file/interactive entry; do not write secret to docs or generated config paths. |
| Database connection | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE`, `DB_URL` | default/keep secret | Bundled stack defaults host/port/name/user; `DB_PASSWORD` is a generated or injected secret. |
| Grafana DB datasource password | `GRAFANA_POSTGRES_SECRET`, `DB_PASSWORD` | merge conditionally | Same credential only for bundled lab/demo Postgres. Production/external DB profiles may split app DB credential and Grafana datasource credential by least privilege. |
| Redis connection | `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_URL` | default/derive | Bundled stack service wiring should not be operator-facing. |
| Queue/cache/session stores | `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `SESSION_*` | default | Application policy defaults, not normal deploy questions. |
| Monitoring login identity | `MONITORING_ADMIN_USERNAME` | keep/default | Can default to `admin` for lab/demo. |
| Monitoring login secret | `MONITORING_PASSWORD`, `GRAFANA_PASSWORD`, `PROMETHEUS_PASSWORD` | merge conditionally | `MONITORING_PASSWORD` is a lab/demo convenience secret. Production may require per-service monitoring credentials or externally managed identity. |
| Monitoring derived hashes | `MONITORING_PASSWORD_HASH`, `PROMETHEUS_PASSWORD_HASH` | derive | Generated from canonical monitoring secret into runtime env. Hashes are secret-adjacent runtime artifacts and must not be logged or committed. |
| Monitoring generated secret path | `GRAFANA_ADMIN_SECRET_FILE` | derive | This is a file path, not a secret value. Derive from `STATE_DIR`. |
| Monitoring generated config paths | `PROMETHEUS_WEB_CONFIG_FILE`, `GRAFANA_DATASOURCES_FILE` | derive | Derive from `STATE_DIR`; keep explicit overrides advanced-only. |
| Monitoring ingress policy | `MONITORING_ACCESS_MODE`, `MONITORING_ALLOWED_CIDRS` | default/move to profile | Mode/profile default for lab/demo; production profile may expose it as operator-owned policy. |
| Auth-service session secret | `SESSION_SECRET` | derive/generate | Secret value for auth service; not a file path. |
| Auth-service port | `PORT` | remove/default | Container-side `3000` should be fixed or internally named, not global operator config. |
| Audit service shared secret | `CANONICAL_AUDIT_AUTH_SERVICE_SECRET`, `CANONICAL_AUDIT_SECRET` | merge/derive secret | One shared HMAC secret; generate unless externally injected. |
| Audit service identity/routing | `CANONICAL_AUDIT_AUTH_SERVICE_KEY_ID`, `CANONICAL_AUDIT_AUTH_SERVICE_ALLOWED_CIDRS`, `CANONICAL_AUDIT_AUTH_SERVICE_ALLOWED_IPS`, `CANONICAL_AUDIT_INGEST_URL`, `AUTH_SERVICE_TRUSTED_PROXY_IPS` | default/derive | Internal service wiring, not normal operator config. |
| Reverse proxy trust | `TRUSTED_PROXIES`, `TRUSTED_PROXY_HEADERS` | derive/default | Derive direct proxy IP/header policy from app-plane profile. Production must fail if derivation would resolve to wildcard trust without an explicit override. |
| TLS mode | `SSL_MODE`, `TARGET_TLS_MODE`, `BT_HOST_TLS_MODE` | derive from mode/move to CLI/profile | Lab/demo derive self-signed or existing local/dev cert behavior. Production uses an explicit TLS contract. Do not expose `SSL_MODE` in normal `.env.example`. |
| TLS cert/key source values | `SSL_CLOUDFLARE_ORIGIN_CERT_FILE`, `SSL_CLOUDFLARE_ORIGIN_KEY_FILE`, `SSL_LETSENCRYPT_CERT_PATH`, `SSL_LETSENCRYPT_KEY_PATH`, `TARGET_NGINX_CERT_PATH`, `TARGET_NGINX_KEY_PATH`, `DEPLOY_NGINX_CERT_PATH`, `DEPLOY_NGINX_KEY_PATH` | merge/advanced | Operator-provided source files are valid advanced inputs. Generated destination paths are not. |
| TLS cert/key generated paths | `BT_NGINX_SSL_*_FILE`, rendered nginx SSL mode paths | derive | Derive from `STATE_DIR`. |
| ACME/DNS-01 configuration | `SSL_ACME_CLIENT`, `SSL_ACME_EMAIL`, `SSL_ACME_CA`, `SSL_ACME_FORCE_RENEW`, `SSL_LETSENCRYPT_CHALLENGE`, `SSL_CERTBOT_CREDENTIALS_FILE`, `CF_Token`, `CF_Zone_ID` | move to CLI/profile/secrets | Advanced production TLS flow, not normal lab/demo env. `CF_Token` is a secret. `CF_Zone_ID` is provider profile metadata, not a secret. |
| State root | `STATE_DIR`, `BT_STATE_DIR`, `INSTALL_BT_STATE_DIR`, `TARGET_BT_STATE_DIR`, `DEPLOY_BT_STATE_DIR` | default/merge | Canonical `STATE_DIR` defaults to `${ROOT_DIR}/.blue-team-vm`; derive tool-specific names during compatibility period. Advanced override only. |
| Generated/runtime dirs | `BT_RUNTIME_DIR`, `BT_BACKUP_DIR`, `BT_MARKER_PATH`, `INSTALL_OUTPUT_DIR`, `OBS_RENDERED_DIR`, `OBS_*_FILE` | derive | From `STATE_DIR`. |
| App/obs network name | `BT_APP_PLANE_NETWORK_NAME`, `COMPOSE_PROJECT_NAME` app-plane fallback | derive | Normal operator should not name Docker networks. Keep override for multi-worktree/manual compose only. |
| Compose file selection | `INSTALL_COMPOSE_FILE`, `BT_COMPOSE_APP_FILE`, `BT_COMPOSE_OBS_FILE`, `BOOTSTRAP_COMPOSE_FILE` | move to CLI/internal | Test/wrapper override only. |
| Container user mapping | `WWWUSER`, `WWWGROUP` | derive | From host `id -u`/`id -g`. |
| CrowdSec optional enrollment | `CROWDSEC_ENROLL_KEY` | keep optional secret | Operator-owned optional external enrollment secret. |
| CrowdSec runtime tuning | `CROWDSEC_DISABLE_ONLINE_API`, `CROWDSEC_DNS_PRIMARY`, `CROWDSEC_DNS_SECONDARY`, `CROWDSEC_REQUIRED_APPSEC_CONFIG`, `CROWDSEC_REQUIRED_APPSEC_COLLECTIONS` | default | Not required for normal deployment. |

## Classification Model For Code PRs

The inventory tables above use shorthand actions to keep PR1 readable. Before
changing behavior, each variable must be classified across three separate axes:

| Field | Allowed values | Meaning |
|---|---|---|
| Ownership | operator / profile / bootstrap / internal | Who owns the value in the supported flow. |
| Lifecycle | required / defaulted / generated / derived / injected | How the value appears before services start. |
| Template action | keep normal / remove normal / advanced doc / compatibility only | Whether the value belongs in the normal `.env.example`. |
| Owner proof source | user prompt / CLI argument / production profile / documented external integration / generated by bootstrap | Why the ownership claim is valid. |

This prevents ambiguous labels such as `default/derive` from being interpreted
as both ownership and lifecycle.

## Application Env Inventory

| Variable | Current role | Classification | Operator-facing target |
|---|---|---|---|
| `APP_NAME` | Laravel display/app name | default | Optional override. |
| `APP_ENV` | Laravel runtime environment | default | Derived from CLI mode. |
| `APP_KEY` | Laravel encryption key | generate/inject | Generate for lab/demo or first-run only when allowed by the mode contract; validate/inject for production; never rotate implicitly. |
| `APP_PREVIOUS_KEYS` | Laravel encryption fallback keys | rotation-only | Managed only by explicit key-rotation flow; never generated during normal bootstrap. |
| `APP_DEBUG` | Debug mode | default | Derived from CLI mode. |
| `APP_URL` | Public app URL | merge | Derived from `APP_DOMAIN`. |
| `ASSET_URL` | Public asset URL/deploy output | derive/remove | Derived from `APP_DOMAIN` or `APP_URL`; remove from normal template unless supported asset-host flow is documented. |
| `APP_DOMAIN` | Proposed canonical domain | keep | Primary public identity after migration. |
| `ADMIN_EMAIL` | Proposed admin email | keep | Primary admin bootstrap identity after migration. |
| `STATE_DIR` | Proposed state root | default | Defaults to `${ROOT_DIR}/.blue-team-vm`; relative overrides resolve against `ROOT_DIR`; advanced override only. |
| `APP_PORT` | Host HTTP binding | default/derive | Internal/local override only. |
| `APP_SSL_PORT` | Host HTTPS binding | default/derive | Internal/local override only. |
| `SSL_MODE` | Container TLS mode | derive/move to CLI/profile | Derived from deployment mode for lab/demo; production TLS contract handles explicit choices. |
| `SSL_CERT_DOMAIN` | TLS certificate CN | merge | Derived from `APP_DOMAIN`. |
| `SSL_CERT_ALT_NAMES` | CA-managed SANs | derive | Derived from `APP_DOMAIN`/profile. |
| `SSL_SELF_SIGNED_ALT_NAMES` | Self-signed SANs | derive | Derived from `APP_DOMAIN`, localhost, lab addresses. |
| `SSL_CLOUDFLARE_ORIGIN_CERT_FILE` | Operator origin cert source path | keep | Advanced TLS input. |
| `SSL_CLOUDFLARE_ORIGIN_KEY_FILE` | Operator origin key source path | keep | Advanced TLS input. |
| `SSL_CLOUDFLARE_ORIGIN_CERT` | Normalized internal cert content/path | remove/derive | Internal compatibility only. |
| `SSL_CLOUDFLARE_ORIGIN_KEY` | Normalized internal key content/path | remove/derive | Internal compatibility only. |
| `SSL_ACME_CLIENT` | ACME client selector | move to CLI/profile | Advanced TLS input. |
| `SSL_ACME_EMAIL` | ACME account email | move to CLI/profile | Required only for ACME production. |
| `SSL_ACME_CA` | ACME CA selector | default | Advanced override. |
| `SSL_ACME_FORCE_RENEW` | Renewal behavior toggle | move to CLI flag | Not normal config. |
| `SSL_LETSENCRYPT_CHALLENGE` | ACME challenge mode | move to CLI/profile | Advanced TLS input. |
| `SSL_CERTBOT_CREDENTIALS_FILE` | DNS credential file path | move to secrets/profile | Advanced TLS input. |
| `CF_Token` | Cloudflare DNS secret | move to secrets | Do not persist as normal app env. |
| `CF_Zone_ID` | Cloudflare DNS zone id | move to profile | Production TLS profile/provider config; not secret, but not normal lab/demo config. |
| `WWWUSER` | Container UID | derive | From host user. |
| `WWWGROUP` | Container GID | derive | From host group. |
| `APP_LOCALE` | Laravel locale | default | Optional app setting. |
| `APP_FALLBACK_LOCALE` | Laravel fallback locale | default | Optional app setting. |
| `APP_FAKER_LOCALE` | Faker locale | default | Test/dev default. |
| `APP_MAINTENANCE_DRIVER` | Maintenance mode driver | default | Not normal operator config. |
| `APP_MAINTENANCE_STORE` | Maintenance store | default | Not normal operator config. |
| `BCRYPT_ROUNDS` | Password hashing cost | default | Production-safe default. |
| `LOG_CHANNEL` | Laravel logging channel | default | Optional advanced override. |
| `LOG_STACK` | Laravel log stack | default | Optional advanced override. |
| `LOG_DEPRECATIONS_CHANNEL` | Deprecation log channel | default | Optional advanced override. |
| `LOG_LEVEL` | Log verbosity | default | Derived from CLI mode. |
| `HTTP_LOGGING_ENABLED` | HTTP log toggle | default | Optional advanced override. |
| `HTTP_LOGGING_SUCCESS` | Success log toggle | default | Optional advanced override. |
| `HTTP_LOGGING_REDIRECTS` | Redirect log toggle | default | Optional advanced override. |
| `HTTP_LOGGING_SLOW_THRESHOLD` | Slow request threshold | default | Optional advanced override. |
| `DB_CONNECTION` | DB driver | default | Bundled stack default `pgsql`. |
| `DB_HOST` | DB service hostname | derive/default | Internal service wiring. |
| `DB_PORT` | DB service port | derive/default | Internal service wiring. |
| `DB_DATABASE` | DB name | default | Optional override. |
| `DB_USERNAME` | DB user | default | Optional override. |
| `DB_PASSWORD` | DB password | generate/inject | Generate for bundled lab/demo or inject from an external DB profile. |
| `DB_SSLMODE` | Postgres SSL mode | default | Derived from DB topology. |
| `INSTALL_GUARD_ENABLED` | Install route guard | default | Derived from mode; production safe. |
| `INSTALL_ALLOWED_IPS` | Install guard allowlist | move to CLI/profile | Only if install route exposed. |
| `INSTALL_TOKEN` | Install guard token | derive/keep secret | Generate if guard enabled. |
| `TRUSTED_PROXIES` | Reverse proxy trust | derive | Derive narrowly from known app-plane reverse proxy addresses/networks. Production must fail if derivation would resolve to wildcard trust without an explicit override. |
| `TRUSTED_PROXY_HEADERS` | Proxy header policy | default | Not normal config. |
| `SESSION_DRIVER` | Laravel session driver | default | Bundled stack default. |
| `SESSION_LIFETIME` | Session lifetime | default | Optional policy override. |
| `SESSION_ENCRYPT` | Session encryption | default | Production-safe default. |
| `SESSION_PATH` | Cookie path | default | Not normal config. |
| `SESSION_DOMAIN` | Cookie domain | derive | From `APP_DOMAIN` when needed. |
| `SESSION_SECURE_COOKIE` | Secure cookie flag | default | Derived from HTTPS mode. |
| `BROADCAST_CONNECTION` | Broadcast driver placeholder | remove | No real consumer found in this project. |
| `FILESYSTEM_DISK` | Storage disk | default | Optional if using S3. |
| `QUEUE_CONNECTION` | Queue driver | default | Bundled stack default. |
| `CACHE_STORE` | Cache backend | default | Bundled stack default. |
| `MEMCACHED_HOST` | Memcached host | remove | Framework-supported but not a supported bundled deployment service. |
| `REDIS_CLIENT` | Redis PHP client | default | Not normal config. |
| `REDIS_HOST` | Redis service hostname | derive/default | Internal service wiring. |
| `REDIS_PASSWORD` | Redis password | inject/profile-only | Not required for bundled unauthenticated Redis; only secure Redis profiles should inject it. |
| `REDIS_PORT` | Redis service port | derive/default | Internal service wiring. |
| `MAIL_MAILER` | Mail driver | default | Lab/demo log mail; production override later. |
| `MAIL_FROM_ADDRESS` | Sender address | default/derive | Lab/demo may derive a placeholder sender from `APP_DOMAIN`. Production mail requires an explicit mail profile or documented external provider contract. |
| `MAIL_FROM_NAME` | Sender name | default | From `APP_NAME`. |
| `AWS_ACCESS_KEY_ID` | S3/AWS credential | move to advanced | Optional integration only; not normal bundled deployment config. |
| `AWS_SECRET_ACCESS_KEY` | S3/AWS credential | move to advanced | Optional integration only; not normal bundled deployment config. |
| `AWS_DEFAULT_REGION` | AWS region | move to advanced/default | Optional integration only; not normal bundled deployment config. |
| `AWS_BUCKET` | S3 bucket | move to advanced | Optional integration only; not normal bundled deployment config. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | S3 endpoint behavior | move to advanced/default | Optional integration only; not normal bundled deployment config. |
| `VITE_APP_NAME` | Frontend app name placeholder | remove | No real consumer found in this project. |
| `VITE_PORT` | Vite dev server port | remove/default | Local dev only, not deployment config. |
| `VITE_HMR_HOST` | Vite HMR host | remove/default | Local dev only. |
| `FORWARD_DB_PORT` | Host DB port | remove/default | Local tooling only. |
| `FORWARD_REDIS_PORT` | Host Redis port | remove/default | Local tooling only. |
| `CROWDSEC_ENROLL_KEY` | CrowdSec console enrollment | keep optional secret | Optional external integration. |
| `CROWDSEC_DISABLE_ONLINE_API` | CrowdSec online API toggle | default | Not normal config. |
| `CROWDSEC_DNS_PRIMARY` | CrowdSec resolver | default | Not normal config. |
| `CROWDSEC_DNS_SECONDARY` | CrowdSec resolver | default | Not normal config. |
| `MONITORING_ACCESS_MODE` | Monitoring ingress policy | default/move to profile | Mode/profile-owned default; advanced/production policy only. |
| `MONITORING_ALLOWED_CIDRS` | Monitoring ingress allowlist | default/move to profile | Mode/profile-owned default; advanced/production policy only. |
| `BT_STATE_DIR` | Current state root | merge/derive | Compatibility alias derived from proposed `STATE_DIR`. |
| `BT_HONEYPOT_SOURCE` | Nginx honeypot include source | derive/default | Internal path. |
| `BT_APP_PLANE_NETWORK_NAME` | Docker app-plane network | derive | Advanced override only. |
| `MONITORING_ADMIN_USERNAME` | Monitoring username | keep/default | Default for lab/demo. |
| `MONITORING_PASSWORD` | Canonical monitoring password | keep/derive secret | Generate or inject. |
| `MONITORING_PASSWORD_HASH` | Monitoring bcrypt output | derive | Derived runtime artifact, secret-adjacent, and must not be logged or committed. |
| `SESSION_SECRET` | Auth service session secret | derive secret | Generate or inject. |
| `GRAFANA_PASSWORD` | Grafana plaintext override | merge | Advanced override; not primary. |
| `GRAFANA_DATASOURCES_FILE` | Generated datasource path | derive | From `STATE_DIR`. |
| `GRAFANA_ADMIN_SECRET_FILE` | Generated Grafana secret path | derive | From `STATE_DIR`. |
| `PROMETHEUS_PASSWORD` | Prometheus plaintext override | merge | Advanced override; not primary. |
| `PROMETHEUS_PASSWORD_HASH` | Prometheus bcrypt output | derive | Derived runtime artifact, secret-adjacent, and must not be logged or committed. |
| `PROMETHEUS_WEB_CONFIG_FILE` | Generated Prometheus config path | derive | From `STATE_DIR`. |
| `CANONICAL_AUDIT_AUTH_SERVICE_SECRET` | Audit HMAC secret | generate/inject | Generate for bundled lab/demo unless an external auth-service profile injects it. |
| `PORT` | Auth-service port | remove/default | Internal container port. |

Current live-consumer note:

- `APP_DOMAIN` is a design target in this inventory, not yet a proven live
  runtime consumer across Laravel, bootstrap, Compose, and deploy flows.
- `compat.shell.env` is currently inventory/bridge scaffolding, not yet part of
  the active runtime execution path.
- `compose.yaml` still acts as a semantic fork from the split app/obs runtime
  path and should not be treated as proof that the canonical model is already
  wired through.

## `.env.example` Consumer Proof Rule

`.env.example` must only contain values that represent the actual supported
configuration contract for this application. A variable may stay in the normal
operator-facing template only if both conditions are true:

1. At least one real consumer exists.
2. The operator owns the value for the normal deployment path.

A real consumer is one of:

- application code or Laravel config used by this project
- bootstrap/init code
- Docker Compose
- deployment script
- documented supported integration

Tests and inventory documents do not count as real consumers. Framework support
also does not automatically count as application support. Laravel may support a
variable by convention, but if this project does not use that feature in a
supported deployment path, the variable should not be in the normal
operator-facing `.env.example`.

Consumption alone is not enough. `DB_HOST` is consumed by Laravel, but for the
bundled lab/demo deployment it is bootstrap/profile-owned service wiring, not an
operator-owned value.

Required cleanup rule for later PRs:

| Case | Consumer exists? | Operator owns value? | Action |
|---|---:|---:|---|
| No real consumer | no | no | remove |
| Future/planned only | no | no | remove |
| Copied from framework template but unsupported by this app | weak/framework-only | no | remove from normal `.env.example` |
| Optional integration | yes, only when enabled | yes, only for that integration | move to advanced/example-specific docs |
| Internal generated path | yes | no | derive from `STATE_DIR` |
| Internal service wiring | yes | no | derive from mode/profile/bootstrap |
| Generated lab/demo secret | yes | no for lab/demo | generate/materialize in bootstrap; do not list in normal `.env.example` |
| Production-only external input | yes | yes for production | production profile/template only |

Initial scan command used for this inventory excluded tests and this document:

```bash
while IFS='=' read -r key _; do
  case "$key" in ''|'#'*) continue;; esac
  [[ "$key" =~ ^[A-Z][A-Z0-9_]*$ ]] || continue
  rg -l "(^|[^A-Z0-9_])${key}([^A-Z0-9_]|$)" \
    app bootstrap config database docker compose.app.yml compose.obs.yml \
    bootstrap-env.sh install.sh ops README.md DEMO_CHEATSHEET.md docs/runbooks
done < .env.example
```

This scan is an inventory aid, not final proof. Each removal must also check
dynamic shell expansion, indirect variable expansion, rendered templates,
Laravel config files, cached-config behavior, entrypoint scripts, and
deployment documentation. Laravel consumer proof must inspect config files and
cached-config behavior, not only raw `env()` or `.env` grep results.

Initial findings:

| Variable/group | Consumer proof result | Classification |
|---|---|---|
| `BROADCAST_CONNECTION` | No real consumer found in application config, bootstrap, Compose, deployment scripts, or supported docs. | remove |
| `VITE_APP_NAME` | No real consumer found in Vite/frontend source, bootstrap, Compose, deployment scripts, or supported docs. | remove |
| `ASSET_URL` | Deployment script output only; no Laravel config consumer found in this scan. | derive/remove from normal template unless a supported asset-host flow is documented |
| `MEMCACHED_HOST` | Laravel cache config supports it, but bundled deployment does not expose Memcached as a supported service. | remove from normal template; advanced only if Memcached becomes supported |
| `AWS_*` | Laravel filesystem/cache/queue/service configs can consume these, but normal bundled deployment does not require AWS. | move to advanced integration example, not normal template |
| Locale/session/logging/cache defaults | Consumed by Laravel config, but mostly framework defaults rather than operator-owned deployment inputs. | default; remove from normal operator template unless project policy requires exposure |
| Local dev port helpers | `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT` are consumed by bootstrap/install/docs only. | local advanced override, not normal deployment template |
| Generated/runtime paths | `GRAFANA_ADMIN_SECRET_FILE`, `GRAFANA_DATASOURCES_FILE`, `PROMETHEUS_WEB_CONFIG_FILE`, `BT_HONEYPOT_SOURCE` have consumers. | derive; remove from normal template |

The implementation PR that edits `.env.example` should include a consumer-proof
and owner-proof table or test for every remaining entry. Dead variables must not
stay as placeholders.

Future hard check:

- add a test or script that parses `.env.example`
- each variable must appear in an approved contract table with:
  - real consumer
  - why the operator owns the value
  - classification
  - validation behavior
  - security note if secret-related
- fail if `.env.example` contains a variable with no approved consumer/owner
  classification

This check exists to prevent `.env.example` from becoming a junk drawer again.

## Bootstrap / Init Requirement

This section records future contract requirements only. PR1 does not implement
them.

A later behavior-changing PR should introduce one dedicated bootstrap/init
contract before normal services start. It can be implemented as one of:

- a Docker Compose init service
- a bootstrap container
- an explicit bootstrap phase inside `./install.sh`

Required behavior:

| Step | Requirement |
|---|---|
| Load mode | Accept mode from command only: `./install.sh lab`, `./install.sh demo`, `./install.sh production`, or an interactive selector when no argument is provided. |
| Apply defaults | Apply lab/demo/production defaults from the semantic role model, not scattered component defaults. |
| Derive internals | Derive internal service URLs, network names, generated directories, rendered config paths, TLS destination paths, and secret-file paths. |
| Generate runtime files | Render nginx, Prometheus, Grafana, auth-service, and TLS runtime config before services start. |
| Write atomically | Write generated config/secrets to temporary files in the same target directory, validate them, then rename into place. |
| Set permissions | Secret files should be owner-readable only where supported, for example `0600`, and generated secret directories must not be world-writable. |
| Generate or locate secrets | Generate missing lab/demo secrets and materialize them into derived runtime/secret files or compatibility env. Validate production secrets or explicit secret-file inputs. |
| Validate external inputs | Fail clearly if required operator-owned values are missing, unsafe, contradictory, or ambiguous. |
| Validate Docker/Compose | Check Docker and Compose availability before writing runtime config. |
| Validate state directories | Ensure `STATE_DIR` and derived runtime/secret/config directories are writable. |
| Validate ports | Validate required published ports and fail on unsafe conflicts unless an explicit non-production override handles local reassignment. |
| Validate dependencies | Validate service dependency configuration before starting application services. |
| Fail fast | Stop before runtime if config is invalid. Do not guess missing production values. |

Normal application services should not start until this bootstrap/init layer
succeeds. The goal is to prevent silent misconfiguration, not to hide invalid
configuration by guessing.

If bootstrap is implemented as a Compose init service, dependent services must
use an explicit readiness/completion gate, not only short-form `depends_on`.
Use long-form dependency conditions such as `service_healthy` or
`service_completed_successfully` where appropriate.
A one-shot bootstrap service should exit zero only after all required runtime
files and compatibility env outputs are written atomically.
The bootstrap PR must verify the project's supported Docker Compose version
supports the chosen dependency condition before relying on it.

Production bootstrap must never rotate existing secrets implicitly. It may
generate a secret only when no persisted secret exists and the selected mode
contract explicitly allows first-run generation. Existing production `APP_KEY`,
database credentials, monitoring credentials, audit HMAC secrets, and TLS
material must be treated as persistent unless an explicit rotation command is
used.

For the later compatibility-bridge implementation PR, bootstrap should be the
bridge:

```text
operator input
  -> selected mode defaults
  -> canonical values
  -> compatibility env/runtime files for existing consumers
```

This lets the operator-facing config shrink without breaking Laravel, Compose,
Nginx, Grafana, Prometheus, or auth-service consumers in one dangerous step.

## Duplicate And Overlap List

- `APP_URL`, `ASSET_URL`, `SSL_CERT_DOMAIN`, `TARGET_DOMAIN`, `DEPLOY_DOMAIN`, and nginx `server_name` are one public application identity role.
- `SSL_CERT_ALT_NAMES` and `SSL_SELF_SIGNED_ALT_NAMES` are certificate SAN derivations from public identity plus lab/local addresses.
- `APP_PORT`, `APP_SSL_PORT`, `TARGET_APP_PORT`, `TARGET_APP_SSL_PORT`, `DEPLOY_APP_PORT`, `DEPLOY_APP_SSL_PORT`, and `DEPLOY_NGINX_PROXY_PASS` are topology/port-binding details.
- `PORT`, `VITE_PORT`, `DB_PORT`, `REDIS_PORT`, `FORWARD_DB_PORT`, and `FORWARD_REDIS_PORT` are internal or local-only service port concepts.
- `BT_STATE_DIR`, `INSTALL_BT_STATE_DIR`, `TARGET_BT_STATE_DIR`, and `DEPLOY_BT_STATE_DIR` are one state-root role.
- `BT_RUNTIME_DIR`, `BT_BACKUP_DIR`, `BT_MARKER_PATH`, `INSTALL_OUTPUT_DIR`, `PROMETHEUS_WEB_CONFIG_FILE`, `GRAFANA_DATASOURCES_FILE`, and `GRAFANA_ADMIN_SECRET_FILE` are derived paths under the state root.
- `MONITORING_PASSWORD`, `GRAFANA_PASSWORD`, and `PROMETHEUS_PASSWORD` are overlapping monitoring secret values. Shared credentials are a lab/demo convenience, not a production default.
- `GRAFANA_ADMIN_SECRET_FILE` is not a Grafana password. It is a generated path pointing to a materialized secret file.
- `DB_PASSWORD` and `GRAFANA_POSTGRES_SECRET` are the same DB credential role only for the bundled lab/demo stack.
- `BT_APP_PLANE_NETWORK_NAME` and compose project fallback are one app/obs network identity role.
- `SSL_MODE`, `TARGET_TLS_MODE`, and `BT_HOST_TLS_MODE` are related TLS mode selectors across container and host boundaries; later PRs must make the boundary explicit without making the operator set three mode names.
- `SETUP_MODE`, `ENV_MODE`, `INSTALL_ENV_MODE`, `BOOTSTRAP_MODE`, and deploy bootstrap mode names are overlapping mode concepts; the operator-facing mode belongs in the CLI command.

## Generated File Paths To Derive From `STATE_DIR`

Given the default:

```dotenv
STATE_DIR=${ROOT_DIR}/.blue-team-vm
```

Lab/demo should derive this automatically. If an advanced override is supplied
as a relative path, resolve it against `ROOT_DIR`, not the caller's current
working directory.

Derive:

| Derived path | Current variable/path |
|---|---|
| runtime directory | `BT_RUNTIME_DIR=${STATE_DIR}/runtime` |
| backup directory | `BT_BACKUP_DIR=${STATE_DIR}/backups` |
| rendered config directory | `${RENDERED_CONFIG_DIR}` derived from `STATE_DIR` |
| install artifacts directory | `${STATE_DIR}/runtime/install-artifacts` |
| host marker path | `BT_MARKER_PATH=${STATE_DIR}/host-baseline-v1.json` |
| nginx SSL runtime directory | `${STATE_DIR}/runtime/nginx-ssl` |
| nginx rendered SSL mode include | `${RENDERED_CONFIG_DIR}/nginx.ssl-mode.conf` |
| nginx active cert/key paths | `${STATE_DIR}/runtime/nginx-ssl/...` |
| Prometheus web config | `${RENDERED_CONFIG_DIR}/prometheus.web-config.yml` |
| Grafana datasources config | `${RENDERED_CONFIG_DIR}/grafana.datasources.yml` |
| Grafana admin secret file | `GRAFANA_ADMIN_SECRET_FILE=${STATE_DIR}/runtime/grafana-admin-secret` |
| obs generated env | `${STATE_DIR}/runtime/obs.generated.env` |
| obs generated audit log | `${STATE_DIR}/runtime/obs.generated-secrets.jsonl` |

Open path convention: current code uses both `${BT_STATE_DIR}/rendered/...` and `${BT_STATE_DIR}/runtime/rendered/...` in different contexts. PR2 must decide whether `RENDERED_CONFIG_DIR=${STATE_DIR}/rendered` or `${STATE_DIR}/runtime/rendered`, then migrate all consumers to that single convention.

Normal operator template target is described above in
`Target Operator Shape After Compatibility Exists`. PR1 does not edit
`.env.example`; it only records what later PRs must prove before shrinking it.

## CLI Contract Design

| Command | Contract |
|---|---|
| `./install.sh lab` | Lab deploy mode. Defaults safe lab values, derives internals, non-destructive by default. |
| `./install.sh demo` | Demo deploy mode. Non-destructive by default. It must not alias to `reset-demo` or wipe data unless an explicit destructive command is used. |
| `./install.sh production` | Production deploy entry point. Fails clearly when a truly required production value or secret is missing. |
| `./install.sh` | Interactive path for operator-owned external values only: deployment mode, `APP_DOMAIN` if missing, and `ADMIN_EMAIL` if needed. |
| `./install.sh reset-demo` | Explicit destructive reset path. Kept separate from `demo`. |

Do not document `DEPLOY_MODE=lab ./install.sh` as the normal path. Deployment mode is a command decision.

During migration, legacy install actions must remain accepted or fail with
explicit migration guidance. Do not silently reinterpret old positional
arguments such as `full`, `demo`, `quick`, `skip`, `setupAdmin`, `test`, or the
current second-position `dev|production` mode.
Legacy destructive `demo` behavior must not be silently preserved under the new
`./install.sh demo` command. If the old destructive flow is still needed,
expose it only as `reset-demo` or behind an explicit compatibility flag.

The interactive path may ask for genuine external values only. It must not
become a general `.env` editor and must not ask for `STATE_DIR` unless an
advanced mode exists, generated paths, internal ports, template paths, Docker
network names, generated secret file paths, service URLs, or service wiring.

## Next Behavior-Changing PR Boundary

The next PR after PR1 should clean the application-facing env model before any
runtime bridge is wired.

It should:

- define the canonical app env mapping table
- treat `ops/bootstrap/app-env-map.json` as the data-only contract source for that mapping table
- classify legacy names as canonical, compatibility alias,
  generated/internal, advanced-only, or removable
- add compatibility mapping validation
- add consumer/owner proof tests
- keep Compose/runtime behavior unchanged
- avoid `.env.example` shrink until compatibility mapping exists

For PR2 Task 3, the approved proof subset is intentionally narrower than the
full `.env.example` inventory. The proof test covers current `.env.example`
entries that Laravel consumes directly through these config files:

- `config/app.php`
- `config/logging.php`
- `config/http_logging.php`
- `config/database.php`
- `config/session.php`
- `config/filesystems.php`
- `config/cache.php`
- `config/queue.php`
- `config/mail.php`
- `config/canonical_audit_ingestion.php`

It also covers the current placeholder/removal candidates called out by the
inventory: `BROADCAST_CONNECTION`, `VITE_APP_NAME`, and `MEMCACHED_HOST`.
Within that proof subset, advanced integration keys such as `AWS_*` must be
marked advanced-only and the removable placeholders above must be marked
removable in `ops/bootstrap/app-env-map.json`. The generated or injected secret
entries `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`, and
`CANONICAL_AUDIT_AUTH_SERVICE_SECRET` must also stop using the
`operator + keep-normal` shape. Their secret-bearing semantic roles should be
declared explicitly in the app env contract metadata, not inferred from fuzzy
name matching. PR2 still does not shrink or delete `.env.example` entries.

It should not:

- route `./install.sh lab|demo|production` into runtime apply
- change Compose startup behavior
- remove current env consumers
- rotate or regenerate existing secrets

## PR2 Handoff Status

PR2 semantic cleanup now has these implemented foundations:

- canonical app env mapping data source at `ops/bootstrap/app-env-map.json`
- compatibility validation via `ops/bootstrap/validate-app-env-map.py`
- consumer/owner proof tests for approved `.env.example` subset
- canonical `STATE_DIR` derivation compatibility in `ops/config/config-contract.yml`
  with `BT_STATE_DIR` alias continuity for existing consumers
- explicit secret semantic role metadata for generated/injected secret policy

PR2 intentionally does not change runtime orchestration:

- no runtime bridge wiring in `install.sh` execution paths
- no Compose startup/gating redesign
- no `.env.example` shrink in this pass
- no secret rotation behavior change

## Blocking Clarifications Before Code PR

Resolve these before any behavior-changing PR:

1. Is `APP_DOMAIN` required for lab/demo, or can mode default to
   `jobs-board.lab`?
2. Is `STATE_DIR` resolved relative to repo root, install root, or caller
   working directory? Proposed answer: `ROOT_DIR`.
3. Which rendered config directory is canonical: `${STATE_DIR}/rendered` or
   `${STATE_DIR}/runtime/rendered`?
4. Can production first-run generate any secrets, or must all production
   secrets be injected?
5. Does production allow shared monitoring credentials, or require per-service
   credentials / managed identity?
6. Are legacy install arguments preserved during migration, and what guidance
   is shown if an old command is deprecated?
7. If bootstrap is implemented as a Compose init service, what exact
   readiness/completion gate prevents app services from starting?

## Migration Risk Notes

- `.env.example` currently drives `bootstrap-env.sh` generation and audit loops; removing entries changes generated/audited secrets. Change it only with focused tests.
- Current tests assert many old variables exist in `.env.example`, compose files, and generated runtime env. Update tests in the same PR that changes behavior.
- `demo` currently aliases to `reset-demo` in `install.sh`; CLI implementation must split non-destructive demo deploy from destructive reset.
- Production bootstrap currently rotates all secrets in `bootstrap-env.sh production`; the production CLI contract must avoid accidental secret rotation during normal deploy.
- Compose currently interpolates generated paths directly from `.env`; later PRs should pass generated runtime env from the bootstrap stage instead of asking operators to set paths.
- Remote deploy scripts use `TARGET_*` and `DEPLOY_*`; keep them as deploy-profile internals until the deploy profile layer is migrated.
- `BT_STATE_DIR` is established in tests and tooling. Introduce `STATE_DIR` as canonical only with a compatibility bridge.
- Some Laravel config variables are framework-default surfaces, not deployment operator surfaces. Do not delete framework support; remove them only from the normal operator template.
- Generated secrets may still be consumed by Laravel or services as env vars internally. Removing them from normal `.env.example` does not mean removing framework support; bootstrap must generate or inject compatibility values for current consumers.
