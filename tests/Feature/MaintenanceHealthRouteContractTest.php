<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

/**
 * Verification path: sqlite-safe.
 */
class MaintenanceHealthRouteContractTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createSettingsTable();
        $this->markSetupCompleted();
        $this->enableMaintenance();
    }

    public function test_health_route_remains_available_during_maintenance(): void
    {
        $this->withBrowser()
            ->get('/up')
            ->assertOk();
    }

    public function test_health_route_does_not_require_settings_table(): void
    {
        Cache::forget('setting.maintenance_mode');
        Schema::drop('settings');

        $this->withBrowser()
            ->get('/up')
            ->assertOk();
    }

    private function markSetupCompleted(): void
    {
        Setting::setBool('setup_completed', true);
    }

    private function enableMaintenance(): void
    {
        Setting::setBool('maintenance_mode', true);
    }
}
