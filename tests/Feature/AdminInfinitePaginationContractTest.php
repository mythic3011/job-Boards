<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminInfinitePaginationContractTest extends TestCase
{
    public function test_admin_paginated_lists_use_shared_infinite_scroll_component(): void
    {
        $users = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/users/index.blade.php');
        $jobs = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/index.blade.php');
        $applications = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/applications/index.blade.php');

        $this->assertIsString($users);
        $this->assertIsString($jobs);
        $this->assertIsString($applications);

        $this->assertStringContainsString('<x-ui.infinite-scroll-pagination', $users);
        $this->assertStringContainsString('<x-ui.infinite-scroll-pagination', $jobs);
        $this->assertStringContainsString('<x-ui.infinite-scroll-pagination', $applications);
        $this->assertStringContainsString('public function loadMore(): void', $users);
        $this->assertStringContainsString('public function loadMore(): void', $jobs);
        $this->assertStringContainsString('public function loadMore(): void', $applications);
    }

    public function test_infinite_scroll_component_and_javascript_expose_auto_fetch_contract(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/ui/infinite-scroll-pagination.blade.php');
        $javascript = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/infinite-pagination.js');

        $this->assertIsString($component);
        $this->assertIsString($javascript);

        $this->assertStringContainsString('data-infinite-pagination', $component);
        $this->assertStringContainsString('data-infinite-pagination-sentinel', $component);
        $this->assertStringContainsString('Load more', $component);
        $this->assertStringContainsString('All caught up', $component);
        $this->assertStringContainsString('IntersectionObserver', $javascript);
        $this->assertStringContainsString('rootMargin', $javascript);
        $this->assertStringContainsString('data-infinite-pagination-button', $javascript);
    }
}
