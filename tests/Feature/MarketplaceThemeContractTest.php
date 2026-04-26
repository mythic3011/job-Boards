<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class MarketplaceThemeContractTest extends TestCase
{
    public function test_jobs_index_uses_theme_aware_search_and_feed_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/jobs/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-input-shell', $contents);
        $this->assertStringContainsString('theme-input', $contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringNotContainsString('rounded-lg border border-gray-200 bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
    }

    public function test_applications_index_uses_theme_aware_search_filter_and_card_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/applications/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-input-shell', $contents);
        $this->assertStringContainsString('theme-input', $contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringNotContainsString('border border-gray-300 bg-white', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
    }

    public function test_job_detail_page_uses_theme_aware_breadcrumb_and_content_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/jobs/show.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-link', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('border-gray-200', $contents);
    }

    public function test_job_create_page_uses_theme_aware_compose_and_salary_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/jobs/create.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringContainsString('theme-panel-subtle', $contents);
        $this->assertStringContainsString('theme-link', $contents);
        $this->assertStringContainsString('Use requirements for must-have skills', $contents);
        $this->assertStringContainsString('Use duties for day-to-day responsibilities', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('ring-gray-300', $contents);
    }

    public function test_job_create_submit_button_exposes_visible_loading_feedback(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/jobs/create.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $contents);
        $this->assertStringContainsString('wire:target="create"', $contents);
        $this->assertStringContainsString('Publishing...', $contents);
        $this->assertStringContainsString('animate-spin', $contents);
    }
}
