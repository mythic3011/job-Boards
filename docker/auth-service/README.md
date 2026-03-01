# Monitoring Auth Service

Simple Express-based auth service for the monitoring front-end. Reads a bcrypt hash from
`MONITORING_PASSWORD_HASH` and issues HTTP-only cookies.

Endpoints:

* `POST /verify` – verify credentials, set session cookie
* `GET /check` – validate existing session (used by nginx auth_request)
* `POST /logout` – clear session
* `GET /health` – simple healthcheck
