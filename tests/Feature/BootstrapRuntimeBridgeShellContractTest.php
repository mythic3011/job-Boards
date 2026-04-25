<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BootstrapRuntimeBridgeShellContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 2);
    }

    public function test_runtime_preload_uses_compat_shell_env_before_dotenv_and_obs_generated_env(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $commonPath = $this->writeCommonFixture($tempRoot);

        file_put_contents($tempRoot.'/.env', "APP_DOMAIN=from-dotenv\nSTATE_DIR=from-dotenv-state\n");
        mkdir($tempRoot.'/runtime', 0777, true);
        file_put_contents($tempRoot.'/runtime/compat.shell.env', "APP_DOMAIN='from-compat'\nSTATE_DIR='from-compat-state'\n");
        file_put_contents($tempRoot.'/runtime/obs.generated.env', "APP_DOMAIN=from-obs\nSTATE_DIR=from-obs-state\n");

        $scriptPath = $tempRoot.'/exercise-preload.sh';
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source "$1"
export BT_RUNTIME_DIR="$2/runtime"
export BT_OBS_GENERATED_ENV_FILE="$2/runtime/obs.generated.env"
export BT_COMPAT_SHELL_ENV_FILE="$2/runtime/compat.shell.env"
bt_preload_compose_env ""
printf '%s\n%s\n' "${APP_DOMAIN:-}" "${STATE_DIR:-}"
BASH;
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        $process = new Process([$scriptPath, $commonPath, $tempRoot], $tempRoot, null, null, 20);
        $process->mustRun();

        $lines = preg_split('/\R/', trim($process->getOutput()));
        $this->assertSame('from-compat', $lines[0] ?? null);
        $this->assertSame('from-compat-state', $lines[1] ?? null);
    }

    public function test_runtime_preload_falls_back_to_dotenv_before_obs_generated_when_compat_is_absent(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $commonPath = $this->writeCommonFixture($tempRoot);

        file_put_contents($tempRoot.'/.env', "APP_DOMAIN=from-dotenv\n");
        mkdir($tempRoot.'/runtime', 0777, true);
        file_put_contents($tempRoot.'/runtime/obs.generated.env', "APP_DOMAIN=from-obs\n");

        $scriptPath = $tempRoot.'/exercise-preload-fallback.sh';
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source "$1"
export BT_RUNTIME_DIR="$2/runtime"
export BT_OBS_GENERATED_ENV_FILE="$2/runtime/obs.generated.env"
export BT_COMPAT_SHELL_ENV_FILE="$2/runtime/missing-compat.shell.env"
bt_preload_compose_env ""
printf '%s\n' "${APP_DOMAIN:-}"
BASH;
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        $process = new Process([$scriptPath, $commonPath, $tempRoot], $tempRoot, null, null, 20);
        $process->mustRun();

        $this->assertSame("from-dotenv\n", $process->getOutput());
    }

    public function test_runtime_preload_does_not_preserve_arbitrary_host_values_over_compat_shell_env(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $commonPath = $this->writeCommonFixture($tempRoot);

        file_put_contents($tempRoot.'/.env', "APP_DOMAIN=from-dotenv\n");
        mkdir($tempRoot.'/runtime', 0777, true);
        file_put_contents($tempRoot.'/runtime/compat.shell.env', "APP_DOMAIN='from-compat'\n");
        file_put_contents($tempRoot.'/runtime/obs.generated.env', "APP_DOMAIN=from-obs\n");

        $scriptPath = $tempRoot.'/exercise-preload-preserve.sh';
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source "$1"
export BT_RUNTIME_DIR="$2/runtime"
export BT_OBS_GENERATED_ENV_FILE="$2/runtime/obs.generated.env"
export BT_COMPAT_SHELL_ENV_FILE="$2/runtime/compat.shell.env"
export APP_DOMAIN="from-host"
bt_preload_compose_env ""
printf '%s\n' "${APP_DOMAIN:-}"
BASH;
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        $process = new Process([$scriptPath, $commonPath, $tempRoot], $tempRoot, null, null, 20);
        $process->mustRun();

        $this->assertSame("from-compat\n", $process->getOutput());
    }

    public function test_runtime_preload_preserves_explicitly_allowed_host_values_over_compat_shell_env(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $commonPath = $this->writeCommonFixture($tempRoot);

        file_put_contents($tempRoot.'/.env', "APP_DOMAIN=from-dotenv\n");
        mkdir($tempRoot.'/runtime', 0777, true);
        file_put_contents($tempRoot.'/runtime/compat.shell.env', "APP_DOMAIN='from-compat'\n");
        file_put_contents($tempRoot.'/runtime/obs.generated.env', "APP_DOMAIN=from-obs\n");

        $scriptPath = $tempRoot.'/exercise-preload-preserve-allowed.sh';
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source "$1"
export BT_RUNTIME_DIR="$2/runtime"
export BT_OBS_GENERATED_ENV_FILE="$2/runtime/obs.generated.env"
export BT_COMPAT_SHELL_ENV_FILE="$2/runtime/compat.shell.env"
export BT_RUNTIME_BRIDGE_PRESERVE_KEYS="APP_DOMAIN"
export APP_DOMAIN="from-host"
bt_preload_compose_env ""
printf '%s\n' "${APP_DOMAIN:-}"
BASH;
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        $process = new Process([$scriptPath, $commonPath, $tempRoot], $tempRoot, null, null, 20);
        $process->mustRun();

        $this->assertSame("from-host\n", $process->getOutput());
    }

    public function test_runtime_preload_does_not_preserve_arbitrary_host_values_for_obs_only_runtime_keys(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $commonPath = $this->writeCommonFixture($tempRoot);

        file_put_contents($tempRoot.'/.env', "APP_DOMAIN=from-dotenv\n");
        mkdir($tempRoot.'/runtime', 0777, true);
        file_put_contents($tempRoot.'/runtime/compat.shell.env', "APP_DOMAIN='from-compat'\n");
        file_put_contents($tempRoot.'/runtime/obs.generated.env', "OBS_ONLY_KEY=from-obs\n");

        $scriptPath = $tempRoot.'/exercise-preload-obs-only.sh';
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source "$1"
export BT_RUNTIME_DIR="$2/runtime"
export BT_OBS_GENERATED_ENV_FILE="$2/runtime/obs.generated.env"
export BT_COMPAT_SHELL_ENV_FILE="$2/runtime/compat.shell.env"
export OBS_ONLY_KEY="from-host"
bt_preload_compose_env ""
printf '%s\n' "${OBS_ONLY_KEY:-}"
BASH;
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        $process = new Process([$scriptPath, $commonPath, $tempRoot], $tempRoot, null, null, 20);
        $process->mustRun();

        $this->assertSame("from-obs\n", $process->getOutput());
    }

    public function test_runtime_preload_does_not_parse_or_depend_on_pr2a_generated_json_state(): void
    {
        $contents = file_get_contents($this->repoRoot.'/ops/lib/common.sh');

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('pr2a.generated.json', $contents);
    }

    public function test_prepare_relative_state_dir_resolves_against_fixture_root_for_compat_shell_env(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $scriptPath = $this->writeBootstrapFixture($tempRoot);

        file_put_contents($tempRoot.'/.env.example', "APP_NAME=Jobs Boards\n");
        file_put_contents($tempRoot.'/.env', "APP_NAME=Jobs Boards\n");

        $process = new Process(
            [$scriptPath, 'prepare', 'lab'],
            $tempRoot,
            [
                'APP_DOMAIN' => 'jobs-board.lab',
                'STATE_DIR' => 'relative/state',
                'BT_BOOTSTRAP_INJECTED_KEYS' => 'APP_DOMAIN,STATE_DIR',
            ],
            null,
            20,
        );
        $process->mustRun();

        $compatPath = $tempRoot.'/relative/state/runtime/compat.shell.env';
        $this->assertFileExists($compatPath);

        $contents = file_get_contents($compatPath);
        $resolvedRoot = (string) realpath($tempRoot);
        $this->assertIsString($contents);
        $this->assertStringContainsString("STATE_DIR='".$resolvedRoot."/relative/state'", $contents);
    }

    public function test_runtime_preload_accepts_export_prefix_and_trailing_comment_in_compat_shell_env(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $commonPath = $this->writeCommonFixture($tempRoot);

        file_put_contents($tempRoot.'/.env', "APP_DOMAIN=from-dotenv\n");
        mkdir($tempRoot.'/runtime', 0777, true);
        file_put_contents($tempRoot.'/runtime/compat.shell.env', "export APP_DOMAIN='from-compat' # comment\n");
        file_put_contents($tempRoot.'/runtime/obs.generated.env', "APP_DOMAIN=from-obs\n");

        $scriptPath = $tempRoot.'/exercise-preload-compat-parser.sh';
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source "$1"
export BT_RUNTIME_DIR="$2/runtime"
export BT_OBS_GENERATED_ENV_FILE="$2/runtime/obs.generated.env"
export BT_COMPAT_SHELL_ENV_FILE="$2/runtime/compat.shell.env"
bt_preload_compose_env ""
printf '%s\n' "${APP_DOMAIN:-}"
BASH;
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        $process = new Process([$scriptPath, $commonPath, $tempRoot], $tempRoot, null, null, 20);
        $process->mustRun();

        $this->assertSame("from-compat\n", $process->getOutput());
    }

    public function test_runtime_preload_preserves_unquoted_hash_in_assignment_values(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $commonPath = $this->writeCommonFixture($tempRoot);

        file_put_contents($tempRoot.'/.env', "APP_DOMAIN=from-dotenv\n");
        mkdir($tempRoot.'/runtime', 0777, true);
        file_put_contents($tempRoot.'/runtime/compat.shell.env', "APP_DOMAIN=from#compat\n");
        file_put_contents($tempRoot.'/runtime/obs.generated.env', "APP_DOMAIN=from-obs\n");

        $scriptPath = $tempRoot.'/exercise-preload-unquoted-hash.sh';
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source "$1"
export BT_RUNTIME_DIR="$2/runtime"
export BT_OBS_GENERATED_ENV_FILE="$2/runtime/obs.generated.env"
export BT_COMPAT_SHELL_ENV_FILE="$2/runtime/compat.shell.env"
bt_preload_compose_env ""
printf '%s\n' "${APP_DOMAIN:-}"
BASH;
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        $process = new Process([$scriptPath, $commonPath, $tempRoot], $tempRoot, null, null, 20);
        $process->mustRun();

        $this->assertSame("from#compat\n", $process->getOutput());
    }

    public function test_runtime_preload_fails_fast_on_invalid_compat_shell_assignment_syntax(): void
    {
        $tempRoot = $this->makeFixtureRoot();
        $commonPath = $this->writeCommonFixture($tempRoot);

        file_put_contents($tempRoot.'/.env', "APP_DOMAIN=from-dotenv\n");
        mkdir($tempRoot.'/runtime', 0777, true);
        file_put_contents($tempRoot.'/runtime/compat.shell.env', "APP_DOMAIN 'missing-equals'\n");
        file_put_contents($tempRoot.'/runtime/obs.generated.env', "APP_DOMAIN=from-obs\n");

        $scriptPath = $tempRoot.'/exercise-preload-invalid-compat.sh';
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
source "$1"
export BT_RUNTIME_DIR="$2/runtime"
export BT_OBS_GENERATED_ENV_FILE="$2/runtime/obs.generated.env"
export BT_COMPAT_SHELL_ENV_FILE="$2/runtime/compat.shell.env"
bt_preload_compose_env ""
BASH;
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        $process = new Process([$scriptPath, $commonPath, $tempRoot], $tempRoot, null, null, 20);
        $process->run();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Invalid shell assignment', $process->getErrorOutput());
    }

    private function makeFixtureRoot(): string
    {
        $dir = sys_get_temp_dir().'/jobs-boards-runtime-bridge-'.bin2hex(random_bytes(8));
        if (! is_dir($dir.'/ops/lib')) {
            mkdir($dir.'/ops/lib', 0777, true);
        }

        return $dir;
    }

    private function writeCommonFixture(string $tempRoot): string
    {
        $commonPath = $tempRoot.'/ops/lib/common.sh';
        copy($this->repoRoot.'/ops/lib/common.sh', $commonPath);
        copy($this->repoRoot.'/ops/lib/config-contract.sh', $tempRoot.'/ops/lib/config-contract.sh');
        chmod($commonPath, 0755);
        chmod($tempRoot.'/ops/lib/config-contract.sh', 0755);

        return $commonPath;
    }

    private function writeBootstrapFixture(string $tempRoot): string
    {
        $scriptPath = $tempRoot.'/bootstrap-env.sh';
        copy($this->repoRoot.'/bootstrap-env.sh', $scriptPath);
        chmod($scriptPath, 0755);

        mkdir($tempRoot.'/ops/bootstrap', 0777, true);
        copy($this->repoRoot.'/ops/bootstrap/contract.json', $tempRoot.'/ops/bootstrap/contract.json');
        copy($this->repoRoot.'/ops/bootstrap/validate-contract.py', $tempRoot.'/ops/bootstrap/validate-contract.py');

        if (! is_dir($tempRoot.'/ops/lib')) {
            mkdir($tempRoot.'/ops/lib', 0777, true);
        }
        copy($this->repoRoot.'/ops/lib/common.sh', $tempRoot.'/ops/lib/common.sh');
        copy($this->repoRoot.'/ops/lib/config-contract.sh', $tempRoot.'/ops/lib/config-contract.sh');

        mkdir($tempRoot.'/ops/bin', 0777, true);
        copy($this->repoRoot.'/ops/bin/resolve-config-contract', $tempRoot.'/ops/bin/resolve-config-contract');
        chmod($tempRoot.'/ops/bin/resolve-config-contract', 0755);

        return $scriptPath;
    }
}
