<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminJobsIndexUiContractTest extends TestCase
{
    public function test_admin_jobs_index_surfaces_refined_salary_copy(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('HK$ {{ $job->salary }}', $contents);
        $this->assertStringContainsString('No salary stated', $contents);
        $this->assertStringContainsString('text-xs italic text-gray-400', $contents);
    }

    public function test_admin_jobs_index_empty_search_state_uses_search_specific_copy(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('Try adjusting your search term', $contents);
    }
}
