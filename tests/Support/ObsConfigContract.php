<?php

declare(strict_types=1);

namespace Tests\Support;

final class ObsConfigContract
{
    public const DEFAULT_STATE_DIR = '.blue-team-vm';
    public const PATH_KEYS = [
        'PROMETHEUS_WEB_CONFIG_FILE',
        'GRAFANA_DATASOURCES_FILE',
        'GRAFANA_ADMIN_SECRET_FILE',
    ];

    public static function manifestPath(): string
    {
        return dirname(__DIR__, 2).'/ops/config/config-contract.yml';
    }

    public static function fallbackExpression(string $key): string
    {
        return match ($key) {
            'PROMETHEUS_WEB_CONFIG_FILE' => '${PROMETHEUS_WEB_CONFIG_FILE:-${BT_STATE_DIR:-.blue-team-vm}/rendered/prometheus.web-config.yml}',
            'GRAFANA_DATASOURCES_FILE' => '${GRAFANA_DATASOURCES_FILE:-${BT_STATE_DIR:-.blue-team-vm}/rendered/grafana.datasources.yml}',
            'GRAFANA_ADMIN_SECRET_FILE' => '${GRAFANA_ADMIN_SECRET_FILE:-${BT_STATE_DIR:-.blue-team-vm}/runtime/grafana-admin-secret}',
            default => throw new \InvalidArgumentException("Unsupported config contract key: {$key}"),
        };
    }

    public static function derivedPath(string $stateDir, string $key): string
    {
        return match ($key) {
            'PROMETHEUS_WEB_CONFIG_FILE' => "{$stateDir}/rendered/prometheus.web-config.yml",
            'GRAFANA_DATASOURCES_FILE' => "{$stateDir}/rendered/grafana.datasources.yml",
            'GRAFANA_ADMIN_SECRET_FILE' => "{$stateDir}/runtime/grafana-admin-secret",
            default => throw new \InvalidArgumentException("Unsupported config contract key: {$key}"),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function derivedPaths(string $stateDir): array
    {
        $paths = [];
        foreach (self::PATH_KEYS as $key) {
            $paths[$key] = self::derivedPath($stateDir, $key);
        }

        return $paths;
    }

    /**
     * @param array<string, string> $extraEntries
     */
    public static function generatedEnvContents(string $stateDir, array $extraEntries = []): string
    {
        $entries = array_merge($extraEntries, self::derivedPaths($stateDir));
        $lines = [];
        foreach ($entries as $key => $value) {
            $lines[] = "{$key}={$value}";
        }

        return implode("\n", $lines)."\n";
    }
}
