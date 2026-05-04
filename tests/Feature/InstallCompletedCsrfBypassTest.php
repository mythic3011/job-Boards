<?php

namespace Tests\Feature;

use App\Models\Setting;
use Tests\Concerns\InteractsWithBrowserRequests;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class InstallCompletedCsrfBypassTest extends TestCase
{
    use InteractsWithBrowserRequests;
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.install_guard_enabled' => true,
            'app.install_allowed_ips' => [],
            'app.install_token' => null,
        ]);

        $this->useInMemorySqlite();
        $this->createSettingsTable();
        $this->createAuditLogsTable();
    }

    public function test_completed_setup_post_install_endpoints_return_404_instead_of_csrf_419(): void
    {
        Setting::setBool('setup_completed', true);

        $this->withBrowser()
            ->post('/install/checks')
            ->assertNotFound();

        $this->withBrowser()
            ->post('/install/complete')
            ->assertNotFound();
    }
}
