<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminJobsIndexUiContractTest extends TestCase
{
    public function test_admin_jobs_index_surfaces_refined_salary_copy(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('{{ $job->salary }}', $contents);
        $this->assertStringContainsString('No salary stated', $contents);
        $this->assertStringContainsString('text-xs italic text-gray-400', $contents);
        $this->assertStringNotContainsString('HK$ {{ $job->salary }}', $contents);
    }

    public function test_admin_jobs_index_empty_state_copy_matches_search_and_filter_context(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('@if($search && ($companyFilter || $sort !== \'latest\'))', $contents);
        $this->assertStringContainsString('Try adjusting your search or filters', $contents);
        $this->assertStringContainsString('@elseif($search)', $contents);
        $this->assertStringContainsString('Try adjusting your search term', $contents);
        $this->assertStringContainsString('@elseif($companyFilter || $sort !== \'latest\')', $contents);
        $this->assertStringContainsString('Try adjusting your filters', $contents);
    }
}
