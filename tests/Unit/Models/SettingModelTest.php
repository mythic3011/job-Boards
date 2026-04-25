<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class SettingModelTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useInMemorySqlite();
    }

    public function test_get_returns_default_when_settings_table_is_missing(): void
    {
        $this->assertSame('fallback', Setting::get('app_name', 'fallback'));
    }

    public function test_get_bool_returns_default_when_settings_table_is_missing(): void
    {
        $this->assertTrue(Setting::getBool('maintenance_mode', true));
        $this->assertFalse(Setting::getBool('maintenance_mode', false));
    }
}

