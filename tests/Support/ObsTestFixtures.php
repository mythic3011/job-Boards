<?php

declare(strict_types=1);

namespace Tests\Support;

final class ObsTestFixtures
{
    public static function installCommonLibFixture(string $repoRoot, string $tempRoot): void
    {
        $commonPath = $tempRoot.'/ops/lib/common.sh';
        $configContractLibPath = $tempRoot.'/ops/lib/config-contract.sh';
        $resolverPath = $tempRoot.'/ops/bin/resolve-config-contract';
        $manifestPath = $tempRoot.'/ops/config/config-contract.yml';

        self::ensureDirectory(dirname($commonPath));
        self::ensureDirectory(dirname($resolverPath));
        self::ensureDirectory(dirname($manifestPath));

        copy($repoRoot.'/ops/lib/common.sh', $commonPath);
        copy($repoRoot.'/ops/lib/config-contract.sh', $configContractLibPath);
        copy($repoRoot.'/ops/bin/resolve-config-contract', $resolverPath);
        copy($repoRoot.'/ops/config/config-contract.yml', $manifestPath);

        chmod($commonPath, 0755);
        chmod($configContractLibPath, 0755);
        chmod($resolverPath, 0755);
    }

    public static function installBootstrapObsFixture(string $repoRoot, string $tempRoot): string
    {
        $scriptPath = $tempRoot.'/ops/bootstrap/bootstrap-obs.sh';
        $datasourceTemplatePath = $tempRoot.'/docker/grafana/provisioning/datasources/datasources.yaml';

        self::ensureDirectory(dirname($scriptPath));
        self::ensureDirectory(dirname($datasourceTemplatePath));

        copy($repoRoot.'/ops/bootstrap/bootstrap-obs.sh', $scriptPath);
        self::installCommonLibFixture($repoRoot, $tempRoot);
        copy($repoRoot.'/docker/grafana/provisioning/datasources/datasources.yaml', $datasourceTemplatePath);

        chmod($scriptPath, 0755);

        return $scriptPath;
    }

    public static function bootstrapObsGeneratedEnvScript(string $stateDir): string
    {
        $generatedEnv = ObsConfigContract::generatedEnvContents($stateDir);

        return <<<BASH
#!/usr/bin/env bash
set -euo pipefail
mkdir -p "\${BT_RUNTIME_DIR}" "\${BT_STATE_DIR}/rendered"
cat > "\${BT_RUNTIME_DIR}/obs.generated.env" <<'EOF'
{$generatedEnv}
EOF
exit 0
BASH;
    }

    public static function materializeProofObsArtifacts(string $stateDir, string $sessionSecret, string $grafanaSecretContents): void
    {
        $obsPaths = ObsConfigContract::derivedPaths($stateDir);

        self::ensureDirectory(dirname($obsPaths['GRAFANA_ADMIN_SECRET_FILE']));
        self::ensureDirectory(dirname($obsPaths['PROMETHEUS_WEB_CONFIG_FILE']));

        file_put_contents($obsPaths['GRAFANA_ADMIN_SECRET_FILE'], $grafanaSecretContents);
        file_put_contents($obsPaths['PROMETHEUS_WEB_CONFIG_FILE'], "basic_auth_users:\n  admin: \"hidden\"\n");
        file_put_contents(
            $stateDir.'/runtime/obs.generated.env',
            ObsConfigContract::generatedEnvContents($stateDir, [
                'SESSION_SECRET' => $sessionSecret,
                'MONITORING_PASSWORD_HASH' => '$2y$12$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'PROMETHEUS_PASSWORD_HASH' => '$2y$12$bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            ]),
        );
    }

    private static function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
