#!/usr/bin/env bash
# Test the public deploy.sh wrapper without requiring private SHP or Docker.
set -Eeuo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
mkdir -p "$tmp/bin" "$tmp/template"
cat > "$tmp/bin/docker" <<'DOCKER'
#!/usr/bin/env bash
# This test exercises the pre-deployment guard; it must not run real containers.
exit 0
DOCKER
cat > "$tmp/template/setup.sh" <<'SHP'
#!/usr/bin/env bash
set -euo pipefail
[ "\${1:-}" = project ] && [ "\${2:-}" = init ]
printf '#!/usr/bin/env bash\n# SHP_PROJECT_ROOT test stub\nexit 0\n' > "$3/setup"
chmod +x "$3/setup"
SHP
chmod +x "$tmp/bin/docker" "$tmp/template/setup.sh"

make_project() {
    local name="$1" bind="$2" extra="\${3:-}"
    mkdir -p "$tmp/$name"
    cp "$root/deploy.sh" "$tmp/$name/deploy.sh"
    cat > "$tmp/$name/.env" <<ENV
PROJECT_NAME=job-boards
PLUGINS=tls-selfsigned job-boards-app
\${bind}
JOB_BOARDS_APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
\${extra}
ENV
}
run_init() {
    local name="$1"
    shift
    (cd "$tmp/$name" && env PATH="$tmp/bin:$PATH" SHP_TEMPLATE_DIR="$tmp/template" "$@" ./deploy.sh init) > "$tmp/$name.out" 2> "$tmp/$name.err"
}
assert_ok() {
    if ! run_init "$@"; then
        printf 'Expected success: %s\n' "$1" >&2
        cat "$tmp/$1.err" >&2
        exit 1
    fi
}
assert_rejected() {
    local name="$1" message="$2"
    if run_init "$name"; then
        printf 'Expected rejection: %s\n' "$name" >&2
        exit 1
    fi
    if ! grep -Fq "$message" "$tmp/$name.err"; then
        printf 'Wrong rejection reason: %s\n' "$name" >&2
        cat "$tmp/$name.err" >&2
        exit 1
    fi
}
make_project local 'BIND_ADDRESS=127.0.0.1'
assert_ok local
make_project public 'BIND_ADDRESS=0.0.0.0'
assert_rejected public 'Refusing public or missing BIND_ADDRESS'
make_project unset ''
assert_rejected unset 'Refusing public or missing BIND_ADDRESS'
make_project public-opt-in 'BIND_ADDRESS=0.0.0.0'
assert_ok public-opt-in JOB_BOARDS_ALLOW_PUBLIC_BIND=1
make_project missing-key 'BIND_ADDRESS=127.0.0.1'
sed -i '/^JOB_BOARDS_APP_KEY=/d' "$tmp/missing-key/.env"
printf 'APP_KEY=legacy-must-be-preserved\n' >> "$tmp/missing-key/.env"
assert_rejected missing-key 'preserve it as JOB_BOARDS_APP_KEY'
make_project legacy-env 'BIND_ADDRESS=127.0.0.1'
sed -i '/^PLUGINS=/d' "$tmp/legacy-env/.env"
assert_rejected legacy-env 'Existing .env is not an SHP config'
printf 'deploy.sh safety guards passed\n'