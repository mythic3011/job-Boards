<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminJobsUiContractTest extends TestCase
{
    public function test_admin_jobs_page_uses_theme_aware_filters_table_and_modal_surfaces(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('theme-panel', $contents);
        $this->assertStringContainsString('theme-input-shell', $contents);
        $this->assertStringContainsString('theme-table-shell', $contents);
        $this->assertStringContainsString('theme-modal-surface', $contents);
        $this->assertStringContainsString('theme-text-strong', $contents);
        $this->assertStringContainsString('theme-text-muted', $contents);
        $this->assertStringNotContainsString('text-gray-900', $contents);
        $this->assertStringNotContainsString('bg-white', $contents);
        $this->assertStringNotContainsString('hover:theme-text-strong', $contents);
    }

    public function test_admin_jobs_delete_trigger_avoids_multi_statement_alpine_handlers(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString("@can('adminModerate', \$job)", $contents);
        $this->assertStringContainsString('Moderation restricted', $contents);
        $this->assertStringContainsString('data-job-id="{{ $job->id }}"', $contents);
        $this->assertStringContainsString('@click="showDeleteModal = !!(pendingDeleteId = $event.currentTarget.dataset.jobId)"', $contents);
        $this->assertStringNotContainsString("@click=\"pendingDeleteId = '{{ \$job->id }}'; showDeleteModal = true\"", $contents);
    }

    public function test_admin_jobs_operator_notes_are_collapsible_with_accessible_toggle(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/admin/jobs/index.blade.php');

        $this->assertIsString($contents);
        $this->assertStringContainsString('grid gap-4 lg:grid-cols-2', $contents);
        $this->assertStringContainsString('aria-controls="jobs-operator-notes"', $contents);
        $this->assertStringContainsString(":aria-expanded=\"notesOpen.toString()\"", $contents);
        $this->assertStringContainsString("x-text=\"notesOpen ? 'Hide notes' : 'Show notes'\"", $contents);
    }
}
