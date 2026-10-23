#!/usr/bin/env bash
# Small, non-destructive launcher for the optional shard-project-helper runtime.
# Does not modify the legacy install.sh, bootstrap-env.sh, or compose files.
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TEMPLATE="${SHP_TEMPLATE_DIR:-$PROJECT_ROOT/vendor/linux_setup/shard-project-helper}"

if [ ! -f "$TEMPLATE/setup.sh" ]; then
    cat >&2 <<EOF
Missing shard-project-helper: $TEMPLATE
Install it in the ignored vendor/ tree, for example:
  git clone git@github.com:mythic3011/linux_setup.git "$PROJECT_ROOT/vendor/linux_setup"
Then re-run: ./deploy/shp/bootstrap.sh
EOF
    exit 1
fi

# Project init only creates the small launcher; it does not destroy any data.
if [ ! -x "$PROJECT_ROOT/setup" ]; then
    "$TEMPLATE/setup.sh" project init "$PROJECT_ROOT"
fi

if [ ! -f "$PROJECT_ROOT/.env" ]; then
    tmp="$(mktemp "$PROJECT_ROOT/.env.XXXXXX")"
    trap 'rm -f "$tmp"' EXIT
    awk '
      /^PROJECT_NAME=/ { print "PROJECT_NAME=job-boards"; next }
      /^PLUGINS=/ { print "PLUGINS=tls-selfsigned job-boards-app"; next }
      /^BIND_ADDRESS=/ { print "BIND_ADDRESS=127.0.0.1"; next }
      { print }
    ' "$TEMPLATE/.env.example" > "$tmp"
    chmod 600 "$tmp"
    mv "$tmp" "$PROJECT_ROOT/.env"
    trap - EXIT
    echo "Created .env: localhost-only; update SERVER_NAME/TLS before publishing."
elif ! grep -Eq '^PLUGINS=.*(^|[[:space:]])job-boards-app([[:space:]]|$)' "$PROJECT_ROOT/.env"; then
    cat >&2 <<'EOF'
Existing .env is not configured for the SHP Job Boards app.
Do not overwrite it: it might contain legacy encryption keys/production settings.
Back it up securely, then create an SHP .env with:
  PLUGINS=tls-selfsigned job-boards-app
  JOB_BOARDS_APP_KEY=<the EXISTING APP_KEY on migrations>
See deploy/shp/README.md.
EOF
    exit 1
fi

cd "$PROJECT_ROOT"
# Init may refresh existing configuration; never permit it to reset a migrated key.
./setup plugin enable job-boards-app
./setup up
./setup check
