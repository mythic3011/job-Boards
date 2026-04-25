<?php

namespace Tests\Feature\Jobs;

use App\Models\JobPosting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Tests\Concerns\UsesInMemorySqlite;
use Tests\TestCase;

class JobsIndexAutocompleteContractTest extends TestCase
{
    use UsesInMemorySqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemorySqlite();
        $this->createUsersTable();
        $this->createSettingsTable();
        $this->createJobPostingsTable();

        Setting::setBool('setup_completed', true);
    }

    public function test_jobs_index_uses_csp_safe_autocomplete_bindings(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/jobs/index.blade.php');

        PhpUnitTestCase::assertIsString($contents);
        PhpUnitTestCase::assertStringContainsString('wire:keydown.arrow-down.prevent="moveSuggestionDown"', $contents);
        PhpUnitTestCase::assertStringContainsString('wire:keydown.arrow-up.prevent="moveSuggestionUp"', $contents);
        PhpUnitTestCase::assertStringContainsString('wire:keydown.enter.prevent="selectHighlightedSuggestion"', $contents);
        PhpUnitTestCase::assertStringContainsString('x-on:click.outside="$wire.hideSuggestions()"', $contents);
        PhpUnitTestCase::assertStringNotContainsString('acCount()', $contents);
        PhpUnitTestCase::assertStringNotContainsString('moveDown() {', $contents);
        PhpUnitTestCase::assertStringNotContainsString('moveUp() {', $contents);
        PhpUnitTestCase::assertStringNotContainsString('selectCurrent()', $contents);
        PhpUnitTestCase::assertStringNotContainsString('setTimeout(() =>', $contents);
    }

    public function test_jobs_index_can_highlight_and_select_suggestions_without_alpine_methods(): void
    {
        $company = $this->createUser([
            'nickname' => 'Acme',
            'user_type' => 'company',
        ]);

        $olderJob = JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_older_'.Str::random(8),
            'company_user_id' => $company->id,
            'title' => 'Laravel Platform Engineer',
            'requirement' => 'Laravel and PHP',
            'duty' => 'Build platform features',
            'salary_from' => 30000,
            'salary_to' => 45000,
        ]);

        $olderJob->forceFill([
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ])->save();

        JobPosting::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'job_newer_'.Str::random(8),
            'company_user_id' => $company->id,
            'title' => 'Laravel QA Analyst',
            'requirement' => 'Laravel QA and testing',
            'duty' => 'Own quality workflows',
            'salary_from' => 28000,
            'salary_to' => 40000,
        ]);

        Volt::test('jobs.index')
            ->set('search', 'Laravel')
            ->assertSet('showSuggestions', true)
            ->assertSet('highlightedSuggestion', -1)
            ->call('moveSuggestionDown')
            ->assertSet('highlightedSuggestion', 0)
            ->call('selectHighlightedSuggestion')
            ->assertSet('search', 'Laravel QA Analyst')
            ->assertSet('showSuggestions', false)
            ->assertSet('highlightedSuggestion', -1)
            ->call('showSuggestionsIfEligible')
            ->assertSet('showSuggestions', true)
            ->call('hideSuggestions')
            ->assertSet('showSuggestions', false)
            ->assertSet('highlightedSuggestion', -1);
    }

    private function createUser(array $attributes): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'idcode' => 'user_'.Str::uuid(),
            'login_id' => Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'nickname' => 'Test User',
            'password' => Hash::make('StrongPass123!'),
            'user_type' => 'individual',
            ...$attributes,
        ]);
    }
}
