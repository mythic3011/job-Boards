# Monitoring Auth Service

Simple Express-based auth service for the monitoring front-end. Reads a bcrypt hash from
`MONITORING_PASSWORD_HASH`, requires `SESSION_SECRET`, and issues HTTP-only cookies.

Startup fails fast if `SESSION_SECRET` is missing or blank. The service does not fall back
to unsigned session cookies.

Endpoints:

* `POST /verify` – verify credentials, set session cookie
* `GET /check` – validate existing session (used by nginx auth_request)
* `POST /logout` – clear session
* `GET /health` – simple healthcheck
