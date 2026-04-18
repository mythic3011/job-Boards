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
        $this->assertStringContainsString('TARGET_DOMAIN="${TARGET_DOMAIN:-jb.mythic3011.com}"', $contents);
        $this->assertStringContainsString('TARGET_HOST="${TARGET_HOST:-66.154.127.33}"', $contents);
        $this->assertStringContainsString('TARGET_APP_PORT="${TARGET_APP_PORT:-127.0.0.1:18080}"', $contents);
        $this->assertStringContainsString('TARGET_APP_SSL_PORT="${TARGET_APP_SSL_PORT:-127.0.0.1:18443}"', $contents);
        $this->assertStringContainsString('TARGET_REMOTE_ROOT="${TARGET_REMOTE_ROOT:-/opt/jobs-boards-jb}"', $contents);
        $this->assertStringContainsString('TARGET_SKIP_HOST_PORT_EXPOSURE_CHECK="${TARGET_SKIP_HOST_PORT_EXPOSURE_CHECK:-true}"', $contents);
        $this->assertStringContainsString('build_reverse_proxy_target', $contents);
    }

    public function test_reverse_proxy_builder_derives_runtime_values_from_domain_and_ip_defaults(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/targets/_builder.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('DEPLOY_DOMAIN="${TARGET_DOMAIN}"', $contents);
        $this->assertStringContainsString('DEPLOY_HOST="${TARGET_HOST}"', $contents);
        $this->assertStringContainsString('TARGET_TLS_MODE="${TARGET_TLS_MODE:-cloudflare-origin}"', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_CERT_DIR="${TARGET_NGINX_CERT_DIR:-/etc/nginx/cert/${DEPLOY_DOMAIN}}"', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_PROXY_PASS="${TARGET_NGINX_PROXY_PASS:-https://127.0.0.1:${DEPLOY_APP_SSL_PORT##*:}/}"', $contents);
        $this->assertStringContainsString('DEPLOY_SKIP_HOST_PORT_EXPOSURE_CHECK="${TARGET_SKIP_HOST_PORT_EXPOSURE_CHECK:-false}"', $contents);
        $this->assertStringContainsString('if [[ "${TARGET_TLS_MODE}" == "letsencrypt" ]]', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_CERT_PATH="${TARGET_NGINX_CERT_PATH:-/etc/letsencrypt/live/${DEPLOY_DOMAIN}/fullchain.pem}"', $contents);
        $this->assertStringContainsString('DEPLOY_NGINX_KEY_PATH="${TARGET_NGINX_KEY_PATH:-/etc/letsencrypt/live/${DEPLOY_DOMAIN}/privkey.pem}"', $contents);
    }

    public function test_from_env_target_exposes_builder_as_generic_domain_ip_profile(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/targets/from-env.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('source "${TARGETS_DIR}/_builder.sh"', $contents);
        $this->assertStringContainsString(': "${TARGET_DOMAIN:?Set TARGET_DOMAIN for the reverse-proxy deploy target}"', $contents);
        $this->assertStringContainsString(': "${TARGET_HOST:?Set TARGET_HOST for the reverse-proxy deploy target}"', $contents);
        $this->assertStringContainsString(': "${TARGET_REMOTE_ROOT:?Set TARGET_REMOTE_ROOT for the reverse-proxy deploy target}"', $contents);
        $this->assertStringContainsString(': "${TARGET_COMPOSE_PROJECT_NAME:?Set TARGET_COMPOSE_PROJECT_NAME for the reverse-proxy deploy target}"', $contents);
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
            ['PATH' => $fakeBin.':'.getenv('PATH')],
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
            ['PATH' => $fakeBin.':'.getenv('PATH')],
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

        $composerOffset = strpos($contents, 'docker compose -f compose.app.yml run --rm --build --no-deps --entrypoint composer laravel.test');
        $appSetupOffset = strpos($contents, './setup-blue-team-vm.sh app');

        $this->assertNotFalse($composerOffset, 'Expected dockerized composer hydration in remote deploy script.');
        $this->assertNotFalse($appSetupOffset, 'Expected app bootstrap call in remote deploy script.');
        $this->assertLessThan($appSetupOffset, $composerOffset, 'Remote deploy must hydrate release dependencies before app bootstrap.');
    }

    public function test_vps_deploy_repairs_legacy_shared_env_drift_from_previous_release_before_relinking(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/deploy/vps-deploy.sh');

        $this->assertIsString($contents);
        $this->assertStringContainsString('repair_shared_env_from_previous_release()', $contents);
        $this->assertStringContainsString("grep -Eq '^DB_PASSWORD=.+'", $contents);
        $this->assertStringContainsString('cp "${previous_env}" "${remote_shared}/.env"', $contents);
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
