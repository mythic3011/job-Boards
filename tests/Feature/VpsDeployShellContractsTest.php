<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class VpsDeployShellContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_vps_deploy_uses_local_git_archive_and_remote_entrypoint_without_remote_git_clone(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('git archive', $contents);
        $this->assertStringContainsString('scp', $contents);
        $this->assertStringContainsString('ssh', $contents);
        $this->assertStringContainsString('SCP_TARGET=(-P "${DEPLOY_SSH_PORT}")', $contents);
        $this->assertStringNotContainsString('git clone', $contents);
        $this->assertStringContainsString('setup-blue-team-vm.sh app', $contents);
        $this->assertStringContainsString('setup-blue-team-vm.sh obs', $contents);
        $this->assertStringNotContainsString('compose.yaml', $contents);
        $this->assertStringContainsString('TARGET_TLS_MODE=cloudflare-origin|letsencrypt|custom', $contents);
        $this->assertStringContainsString('Missing TLS certificate file', $contents);
        $this->assertStringContainsString('Missing TLS private key file', $contents);
    }

    public function test_vps_deploy_target_is_repeatable_loopback_high_port_contract_for_jb_domain(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/targets/jb.mythic3011.com.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('source "${TARGETS_DIR}/_builder.sh"', $contents);
        $this->assertStringContainsString('TARGET_SUBDOMAIN="${TARGET_SUBDOMAIN:-jb}"', $contents);
        $this->assertStringContainsString('TARGET_ROOT_DOMAIN="${TARGET_ROOT_DOMAIN:-mythic3011.com}"', $contents);
        $this->assertStringContainsString('TARGET_DOMAIN="${TARGET_DOMAIN:-${TARGET_SUBDOMAIN}.${TARGET_ROOT_DOMAIN}}"', $contents);
        $this->assertStringContainsString(': "${TARGET_HOST:?Set TARGET_HOST for jb.mythic3011.com deploy target}"', $contents);
        $this->assertStringContainsString('TARGET_APP_PORT="${TARGET_APP_PORT:-127.0.0.1:18080}"', $contents);
        $this->assertStringContainsString('TARGET_APP_SSL_PORT="${TARGET_APP_SSL_PORT:-127.0.0.1:18443}"', $contents);
        $this->assertStringContainsString('TARGET_REMOTE_ROOT="${TARGET_REMOTE_ROOT:-/opt/jobs-boards-jb}"', $contents);
        $this->assertStringContainsString('TARGET_SKIP_HOST_PORT_EXPOSURE_CHECK="${TARGET_SKIP_HOST_PORT_EXPOSURE_CHECK:-true}"', $contents);
        $this->assertStringContainsString('TARGET_NGINX_CERT_DOMAIN="${TARGET_NGINX_CERT_DOMAIN:-${TARGET_ROOT_DOMAIN}}"', $contents);
        $this->assertStringContainsString('build_reverse_proxy_target', $contents);
    }

    public function test_reverse_proxy_builder_derives_runtime_values_from_domain_and_ip_defaults(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/targets/_builder.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('resolve_target_domain()', $contents);
        $this->assertStringContainsString('printf \'%s.%s\\n\' "${TARGET_SUBDOMAIN}" "${TARGET_ROOT_DOMAIN}"', $contents);
        $this->assertStringContainsString('DEPLOY_DOMAIN="$(resolve_target_domain)" || return 1', $contents);
        $this->assertStringContainsString('DEPLOY_HOST="${TARGET_HOST}"', $contents);
        $this->assertStringContainsString('TARGET_TLS_MODE="${TARGET_TLS_MODE:-cloudflare-origin}"', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_CERT_DOMAIN="${TARGET_NGINX_CERT_DOMAIN:-${TARGET_ROOT_DOMAIN:-${DEPLOY_DOMAIN}}}"', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_CERT_DIR="${TARGET_NGINX_CERT_DIR:-/etc/nginx/cert/${DEPLOY_NGINX_CERT_DOMAIN}}"', $contents);
        $this->assertStringContainsString('deploy_expand_path_template()', $contents);
        $this->assertStringContainsString('build_reverse_proxy_tls_paths()', $contents);
        $this->assertStringContainsString('build_reverse_proxy_tls_paths "${TARGET_TLS_MODE}" "${DEPLOY_NGINX_CERT_DOMAIN}" || return 1', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_PROXY_PASS="${TARGET_NGINX_PROXY_PASS:-https://127.0.0.1:${DEPLOY_APP_SSL_PORT##*:}/}"', $contents);
        $this->assertStringContainsString('DEPLOY_SKIP_HOST_PORT_EXPOSURE_CHECK="${TARGET_SKIP_HOST_PORT_EXPOSURE_CHECK:-false}"', $contents);
        $this->assertStringContainsString("default_cert_template='/etc/letsencrypt/live/{domain}/fullchain.pem'", $contents);
        $this->assertStringContainsString("default_key_template='/etc/letsencrypt/live/{domain}/privkey.pem'", $contents);
        $this->assertStringContainsString("default_cert_template='/etc/nginx/cert/{domain}/cert.pem'", $contents);
        $this->assertStringContainsString("default_key_template='/etc/nginx/cert/{domain}/key.pem'", $contents);
        $this->assertStringContainsString('cert_template="${TARGET_NGINX_CERT_PATH_TEMPLATE:-${default_cert_template}}"', $contents);
        $this->assertStringContainsString('key_template="${TARGET_NGINX_KEY_PATH_TEMPLATE:-${default_key_template}}"', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_CERT_PATH="${TARGET_NGINX_CERT_PATH:-$(deploy_expand_path_template "${cert_template}" "${cert_domain}")}"', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_KEY_PATH="${TARGET_NGINX_KEY_PATH:-$(deploy_expand_path_template "${key_template}" "${cert_domain}")}"', $contents);
    }

    public function test_reverse_proxy_builder_expands_cert_domain_placeholders_at_runtime(): void
    {
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source ops/deploy/targets/_builder.sh
TARGET_DOMAIN="jb.mythic3011.com"
TARGET_HOST="203.0.113.10"
TARGET_REMOTE_ROOT="/opt/jobs-boards-jb"
TARGET_COMPOSE_PROJECT_NAME="jobs-boards-jb"
TARGET_NGINX_CERT_DOMAIN="mythic3011.com"
build_reverse_proxy_target
printf '%s\n%s\n' "${DEPLOY_NGINX_CERT_PATH}" "${DEPLOY_NGINX_KEY_PATH}"
BASH;

        $process = Process::fromShellCommandline($script, $this->repoRoot, null, null, 10);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame("/etc/nginx/cert/mythic3011.com/cert.pem\n/etc/nginx/cert/mythic3011.com/key.pem\n", $process->getOutput());
    }

    public function test_reverse_proxy_builder_can_compose_domain_from_subdomain_and_root_domain(): void
    {
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source ops/deploy/targets/_builder.sh
TARGET_SUBDOMAIN="jb"
TARGET_ROOT_DOMAIN="mythic3011.com"
TARGET_HOST="203.0.113.10"
TARGET_REMOTE_ROOT="/opt/jobs-boards-jb"
TARGET_COMPOSE_PROJECT_NAME="jobs-boards-jb"
build_reverse_proxy_target
printf '%s\n%s\n' "${DEPLOY_DOMAIN}" "${DEPLOY_NGINX_CERT_DOMAIN}"
BASH;

        $process = Process::fromShellCommandline($script, $this->repoRoot, null, null, 10);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame("jb.mythic3011.com\nmythic3011.com\n", $process->getOutput());
    }

    public function test_from_env_target_exposes_builder_as_generic_domain_ip_profile(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/targets/from-env.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('source "${TARGETS_DIR}/_builder.sh"', $contents);
        $this->assertStringContainsString(': "${TARGET_HOST:?Set TARGET_HOST for the reverse-proxy deploy target}"', $contents);
        $this->assertStringContainsString(': "${TARGET_REMOTE_ROOT:?Set TARGET_REMOTE_ROOT for the reverse-proxy deploy target}"', $contents);
        $this->assertStringContainsString(': "${TARGET_COMPOSE_PROJECT_NAME:?Set TARGET_COMPOSE_PROJECT_NAME for the reverse-proxy deploy target}"', $contents);
        $this->assertStringContainsString('Set TARGET_DOMAIN or TARGET_SUBDOMAIN + TARGET_ROOT_DOMAIN for the reverse-proxy deploy target', $contents);
        $this->assertStringContainsString('build_reverse_proxy_target', $contents);
    }

    public function test_lab_target_is_parameterized_for_repeatable_non_production_direct_bind_deployments(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/targets/lab-env.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('LAB_DEPLOY_HOST', $contents);
        $this->assertStringContainsString('LAB_WAN_MODE="${LAB_WAN_MODE:-dhcp}"', $contents);
        $this->assertStringContainsString('LAB_WAN_IFACE="${LAB_WAN_IFACE:-eth0}"', $contents);
        $this->assertStringContainsString('LAB_LAN_IFACE="${LAB_LAN_IFACE:-eth1}"', $contents);
        $this->assertStringContainsString('LAB_NETPLAN_APPLY="${LAB_NETPLAN_APPLY:-false}"', $contents);
        $this->assertStringContainsString('DEPLOY_INSTALL_HOST_NGINX="false"', $contents);
        $this->assertStringContainsString('DEPLOY_APP_PORT="${LAB_DEPLOY_APP_PORT:-80}"', $contents);
        $this->assertStringContainsString('DEPLOY_APP_SSL_PORT="${LAB_DEPLOY_APP_SSL_PORT:-443}"', $contents);
    }

    public function test_vps_deploy_supports_lab_wan_dhcp_or_static_plus_optional_lan_subnet_rendering(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('LAB_WAN_MODE', $contents);
        $this->assertStringContainsString('LAB_WAN_IFACE', $contents);
        $this->assertStringContainsString('LAB_LAN_IFACE', $contents);
        $this->assertStringContainsString('LAB_LAN_ADDRESS', $contents);
        $this->assertStringContainsString('netplan generate', $contents);
        $this->assertStringContainsString('if [[ "${LAB_NETPLAN_APPLY:-false}" == "true" ]]', $contents);
    }

    public function test_vps_deploy_persists_monitoring_access_policy_into_remote_env_and_release_env(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('--describe', $contents);
        $this->assertStringContainsString('DEPLOY_PROFILE_NAME', $contents);
        $this->assertStringContainsString('DEPLOY_PROFILE_KIND', $contents);
        $this->assertStringContainsString('DEPLOY_MONITORING_ACCESS_MODE', $contents);
        $this->assertStringContainsString('DEPLOY_MONITORING_ALLOWED_CIDRS', $contents);
        $this->assertStringContainsString('"MONITORING_ACCESS_MODE": os.environ["DEPLOY_MONITORING_ACCESS_MODE"]', $contents);
        $this->assertStringContainsString('"MONITORING_ALLOWED_CIDRS": os.environ["DEPLOY_MONITORING_ALLOWED_CIDRS"]', $contents);
    }

    public function test_vps_deploy_describe_prints_operator_profile_summary_without_remote_access(): void
    {
        $process = new Process(
            [$this->repoRoot.'/ops/deploy/vps-deploy.sh', '--describe', 'jb.mythic3011.com'],
            $this->repoRoot,
            [
                'TARGET_HOST' => '203.0.113.10',
            ],
            null,
            10,
        );
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $output = $process->getOutput();
        $this->assertStringContainsString('Profile: jb.mythic3011.com', $output);
        $this->assertStringContainsString('Kind: reverse-proxy', $output);
        $this->assertStringContainsString('Domain: jb.mythic3011.com', $output);
        $this->assertStringContainsString('Install host nginx: true', $output);
        $this->assertStringContainsString('Monitoring access mode: auth-only', $output);
        $this->assertStringContainsString('Operator credentials: MONITORING_ADMIN_USERNAME, MONITORING_PASSWORD', $output);
    }

    public function test_vps_deploy_selects_production_bootstrap_for_first_init_and_dev_for_repeat_runs(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->deployScriptFixture($tempRoot);
        $gitLog = $tempRoot.'/git.log';
        $scpLog = $tempRoot.'/scp.log';
        $sshLog = $tempRoot.'/ssh.log';
        $capturedEnv = $tempRoot.'/captured.remote.env';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/deploy/targets', 0777, true);
        mkdir($tempRoot.'/ops/deploy/templates', 0777, true);

        copy($this->repoRoot.'/ops/deploy/targets/jb.mythic3011.com.sh', $tempRoot.'/ops/deploy/targets/jb.mythic3011.com.sh');
        copy($this->repoRoot.'/ops/deploy/targets/_builder.sh', $tempRoot.'/ops/deploy/targets/_builder.sh');
        copy($this->repoRoot.'/ops/deploy/templates/nginx-site.conf.tpl', $tempRoot.'/ops/deploy/templates/nginx-site.conf.tpl');

        $this->writeExecutable($fakeBin.'/git', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$gitLog}"
if [[ "\${1:-}" == "status" ]]; then
  exit 0
fi
if [[ "\${1:-}" == "rev-parse" ]]; then
  echo "0123456789abcdef0123456789abcdef01234567"
  exit 0
fi
if [[ "\${1:-}" == "archive" ]]; then
  output=""
  for arg in "\$@"; do
    case "\${arg}" in
      --output=*) output="\${arg#--output=}" ;;
    esac
  done
  printf 'archive' > "\${output}"
  exit 0
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/scp', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$scpLog}"
for arg in "\$@"; do
  case "\$(basename "\${arg}")" in
    deploy.remote.env)
      cp "\${arg}" "{$capturedEnv}"
      ;;
  esac
done
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/ssh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$sshLog}"
if printf '%s\n' "\$*" | grep -q "test -f '/opt/jobs-boards-jb/shared/.env'"; then
  exit 1
fi
exit 0
BASH);

        $first = new Process(
            [$scriptPath, 'jb.mythic3011.com'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'TARGET_HOST' => '203.0.113.10',
            ],
            null,
            20,
        );
        $first->run();

        $this->assertSame(0, $first->getExitCode(), $first->getOutput().$first->getErrorOutput());
        $this->assertStringContainsString('archive --format=tar.gz', (string) file_get_contents($gitLog));
        $this->assertStringContainsString("DEPLOY_BOOTSTRAP_MODE='production'", (string) file_get_contents($sshLog));

        file_put_contents($sshLog, '');
        $this->writeExecutable($fakeBin.'/ssh', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "\$*" >> "{$sshLog}"
exit 0
BASH);

        $second = new Process(
            [$scriptPath, 'jb.mythic3011.com'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'TARGET_HOST' => '203.0.113.10',
            ],
            null,
            20,
        );
        $second->run();

        $this->assertSame(0, $second->getExitCode(), $second->getOutput().$second->getErrorOutput());
        $this->assertStringContainsString("DEPLOY_BOOTSTRAP_MODE='dev'", (string) file_get_contents($sshLog));
    }

    public function test_vps_deploy_quotes_remote_env_values_for_shell_sensitive_install_inputs(): void
    {
        $tempRoot = $this->makeTempDir();
        $scriptPath = $this->deployScriptFixture($tempRoot);
        $capturedEnv = $tempRoot.'/captured.remote.env';
        $fakeBin = $tempRoot.'/fake-bin';

        mkdir($fakeBin, 0777, true);
        mkdir($tempRoot.'/ops/deploy/targets', 0777, true);
        mkdir($tempRoot.'/ops/deploy/templates', 0777, true);

        copy($this->repoRoot.'/ops/deploy/targets/jb.mythic3011.com.sh', $tempRoot.'/ops/deploy/targets/jb.mythic3011.com.sh');
        copy($this->repoRoot.'/ops/deploy/targets/_builder.sh', $tempRoot.'/ops/deploy/targets/_builder.sh');
        copy($this->repoRoot.'/ops/deploy/templates/nginx-site.conf.tpl', $tempRoot.'/ops/deploy/templates/nginx-site.conf.tpl');

        $this->writeExecutable($fakeBin.'/git', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == "status" ]]; then
  exit 0
fi
if [[ "${1:-}" == "rev-parse" ]]; then
  echo "0123456789abcdef0123456789abcdef01234567"
  exit 0
fi
if [[ "${1:-}" == "archive" ]]; then
  output=""
  for arg in "$@"; do
    case "${arg}" in
      --output=*) output="${arg#--output=}" ;;
    esac
  done
  printf 'archive' > "${output}"
  exit 0
fi
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/scp', <<<BASH
#!/usr/bin/env bash
set -euo pipefail
for arg in "\$@"; do
  case "\$(basename "\${arg}")" in
    deploy.remote.env)
      cp "\${arg}" "{$capturedEnv}"
      ;;
  esac
done
exit 0
BASH);

        $this->writeExecutable($fakeBin.'/ssh', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
exit 0
BASH);

        $process = new Process(
            [$scriptPath, 'jb.mythic3011.com'],
            $tempRoot,
            [
                'PATH' => $fakeBin.':'.getenv('PATH'),
                'TARGET_HOST' => '203.0.113.10',
                'JB_INSTALL_ADMIN_EMAIL' => 'me@mythi3011.com',
                'JB_INSTALL_ADMIN_PASSWORD' => 'V%U7&HX7A#N@eFvH',
            ],
            null,
            20,
        );
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertStringContainsString("JB_INSTALL_ADMIN_PASSWORD=V%U7\\&HX7A#N@eFvH\n", (string) file_get_contents($capturedEnv));
    }

    public function test_vps_deploy_hydrates_release_dependencies_before_app_bootstrap(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('docker compose -f compose.app.yml run --rm --build --no-deps --entrypoint composer laravel.test', $contents);
        $this->assertStringContainsString('install --no-interaction --prefer-dist --no-dev --optimize-autoloader', $contents);
        $this->assertStringContainsString("docker compose -f compose.app.yml run --rm --build --no-deps --entrypoint sh laravel.test \\", $contents);
        $this->assertStringContainsString("-lc 'npm ci --no-audit --no-fund && npm run build && rm -rf node_modules'", $contents);

        $composerOffset = strpos($contents, 'docker compose -f compose.app.yml run --rm --build --no-deps --entrypoint composer laravel.test');
        $frontendOffset = strpos($contents, "docker compose -f compose.app.yml run --rm --build --no-deps --entrypoint sh laravel.test");
        $appSetupOffset = strpos($contents, './setup-blue-team-vm.sh app');

        $this->assertNotFalse($composerOffset, 'Expected dockerized composer hydration in remote deploy script.');
        $this->assertNotFalse($frontendOffset, 'Expected dockerized frontend asset hydration in remote deploy script.');
        $this->assertNotFalse($appSetupOffset, 'Expected app bootstrap call in remote deploy script.');
        $this->assertLessThan($appSetupOffset, $composerOffset, 'Remote deploy must hydrate release dependencies before app bootstrap.');
        $this->assertLessThan($appSetupOffset, $frontendOffset, 'Remote deploy must build frontend assets before app bootstrap.');
    }

    public function test_vps_deploy_repairs_legacy_shared_env_drift_from_previous_release_before_relinking(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('repair_shared_env_from_previous_release()', $contents);
        $this->assertStringContainsString("grep -Eq '^DB_PASSWORD=.+'", $contents);
        $this->assertStringContainsString('cp "${previous_env}" "${remote_shared}/.env"', $contents);
    }

    public function test_vps_deploy_materializes_release_env_from_shared_instead_of_absolute_symlink(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('materialize_release_env()', $contents);
        $this->assertStringContainsString('cp "${remote_shared}/.env" "${remote_release}/.env"', $contents);
        $this->assertStringNotContainsString('ln -sfn "${remote_shared}/.env" "${remote_release}/.env"', $contents);
        $this->assertStringContainsString('python3 - "${remote_release}/.env"', $contents);
    }

    public function test_vps_deploy_syncs_release_env_back_to_shared_after_bootstrap_and_install(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('sync_release_env_to_shared()', $contents);
        $this->assertStringContainsString('cmp -s "${release_env}" "${remote_shared}/.env" && return 0', $contents);
        $this->assertSame(3, substr_count($contents, 'sync_release_env_to_shared'));
    }

    public function test_vps_deploy_uses_shared_compose_preload_for_honeypot_source_instead_of_target_local_export(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('bt_preload_compose_env', $contents);
        $this->assertStringContainsString('source_release_common()', $contents);
        $this->assertStringContainsString('source "${remote_release}/ops/lib/common.sh"', $contents);
        $this->assertStringNotContainsString('source "${remote_current}/ops/lib/common.sh"', $contents);
        $this->assertStringNotContainsString('export BT_HONEYPOT_SOURCE="${remote_current}/docker/nginx/includes/blue-team-honeypot.conf"', $contents);
        $this->assertMatchesRegularExpression(
            '/export BT_STATE_DIR="\$\{DEPLOY_BT_STATE_DIR\}"\n'
            .'export BT_RUNTIME_DIR="\$\{runtime_dir\}"\n'
            .'.*'
            .'source_release_common\n'
            .'.*'
            .'bt_preload_compose_env\n\n'
            .'hydrate_release_dependencies/s',
            $contents,
        );
    }

    public function test_vps_deploy_restarts_laravel_runtime_after_bootstrap_and_headless_install_before_final_proof(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('docker compose -f compose.app.yml restart laravel.test', $contents);
        $this->assertStringContainsString('bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" laravel.test healthy 120', $contents);
        $this->assertStringContainsString('bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" nginx healthy 120', $contents);

        $installOffset = strpos($contents, 'docker compose -f compose.app.yml exec -T laravel.test "${install_args[@]}"');
        $restartOffset = strpos($contents, 'docker compose -f compose.app.yml restart laravel.test');
        $waitLaravelOffset = strpos($contents, 'bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" laravel.test healthy 120');
        $waitNginxOffset = strpos($contents, 'bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" nginx healthy 120');
        $this->assertStringContainsString('curl --retry 10 --retry-delay 2 --retry-all-errors -kfsS \\', $contents);
        $finalCurlOffset = strpos($contents, '--resolve "${DEPLOY_DOMAIN}:443:127.0.0.1" "https://${DEPLOY_DOMAIN}/up" >/dev/null');

        $this->assertNotFalse($restartOffset, 'Expected remote deploy to refresh the laravel runtime before final proof.');
        $this->assertNotFalse($waitLaravelOffset, 'Expected deploy to wait for laravel.test health after restart.');
        $this->assertNotFalse($waitNginxOffset, 'Expected deploy to wait for nginx health after restart.');
        $this->assertNotFalse($finalCurlOffset, 'Expected final deploy proof curl in remote deploy script.');
        $this->assertFalse($installOffset === false || $restartOffset < $installOffset, 'Laravel restart must happen after optional headless install.');
        $this->assertLessThan($waitLaravelOffset, $restartOffset, 'Laravel restart must happen before laravel.test health wait.');
        $this->assertLessThan($waitNginxOffset, $waitLaravelOffset, 'laravel.test health wait must happen before nginx health wait.');
        $this->assertLessThan($finalCurlOffset, $restartOffset, 'Laravel restart must happen before the final front-door proof.');
    }

    public function test_vps_deploy_repairs_release_writable_dir_ownership_for_php_fpm_before_bootstrap_and_restart(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('prepare_release_runtime_permissions()', $contents);
        $this->assertStringContainsString('mkdir -p "${writable_dirs[@]}"', $contents);
        $this->assertStringContainsString('chown -R 1337:1000', $contents);
        $this->assertStringContainsString('chmod -R ug+rwX', $contents);
        $this->assertStringContainsString('chown 1337:1000 "${remote_release}/.env"', $contents);
        $this->assertStringContainsString('chmod 0640 "${remote_release}/.env"', $contents);
        $this->assertSame(4, substr_count($contents, 'prepare_release_runtime_permissions'));
    }

    public function test_vps_deploy_normalizes_release_env_permissions_immediately_after_bootstrap_env_mutation(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString(<<<'BASH'
if [[ "${DEPLOY_BOOTSTRAP_MODE}" == "production" ]]; then
    ./bootstrap-env.sh production
else
    ./bootstrap-env.sh dev
fi
prepare_release_runtime_permissions
sync_release_env_to_shared
BASH, $contents);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-vps-deploy-'.bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function deployScriptFixture(string $tempRoot): string
    {
        $scriptPath = $tempRoot.'/ops/deploy/vps-deploy.sh';
        mkdir(dirname($scriptPath), 0777, true);
        copy($this->repoRoot.'/ops/deploy/vps-deploy.sh', $scriptPath);
        chmod($scriptPath, 0755);

        return $scriptPath;
    }

    private function writeExecutable(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $contents);
        chmod($path, 0755);
    }
}
