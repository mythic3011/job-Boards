<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (!Schema::hasTable('settings') || !Setting::isSetupCompleted()) {
            return redirect()->route('install.index');
        }

        $user = $request->user();

        if (!$user) {
            return view('welcome', $this->guestPayload());
        }

        if ($user->isAdmin()) {
            return view('welcome', $this->adminPayload());
        }

        if ($user->isCompany()) {
            return view('welcome', $this->companyPayload($user));
        }

        return view('welcome', $this->individualPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function guestPayload(): array
    {
        return [
            'pageTitle' => 'Welcome to '.config('app.name', 'Jobs Board'),
            'homeSurface' => 'guest',
            'recentJobs' => JobPosting::query()
                ->with('companyUser')
                ->latest()
                ->take(6)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(): array
    {
        return [
            'pageTitle' => 'Admin Home',
            'homeSurface' => 'admin',
            'adminLinks' => [
                [
                    'label' => 'Open Admin Dashboard',
                    'description' => 'Jump into the operational snapshot with platform-wide signals.',
                    'href' => route('admin.dashboard'),
                ],
                [
                    'label' => 'Review Users',
                    'description' => 'Inspect account hygiene, lockouts, and moderation tasks.',
                    'href' => route('admin.users.index'),
                ],
                [
                    'label' => 'Check Jobs',
                    'description' => 'Review live listings and marketplace quality.',
                    'href' => route('admin.jobs.index'),
                ],
                [
                    'label' => 'Inspect Applications',
                    'description' => 'Watch the response queue and dispute surfaces.',
                    'href' => route('admin.applications.index'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function individualPayload(User $user): array
    {
        $applications = Application::query()->byApplicant($user->id);

        $submittedCount = (clone $applications)->count();
        $pendingCount = (clone $applications)->where('status', ApplicationStatus::PENDING->value)->count();
        $approvedCount = (clone $applications)->where('status', ApplicationStatus::APPROVED->value)->count();
        $rejectedCount = (clone $applications)->where('status', ApplicationStatus::REJECTED->value)->count();

        return [
            'pageTitle' => 'Career Dashboard',
            'homeSurface' => 'individual',
            'summaryCards' => [
                [
                    'label' => 'Applications Sent',
                    'value' => number_format($submittedCount),
                    'description' => 'Your submitted opportunities across the platform.',
                ],
                [
                    'label' => 'In Review',
                    'value' => number_format($pendingCount),
                    'description' => 'Applications still waiting on a company decision.',
                ],
                [
                    'label' => 'Approved',
                    'value' => number_format($approvedCount),
                    'description' => 'Applications that advanced to a positive outcome.',
                ],
            ],
            'applicationPipeline' => [
                [
                    'label' => 'Pending review',
                    'value' => $pendingCount,
                    'description' => 'Still active in the company queue.',
                ],
                [
                    'label' => 'Approved',
                    'value' => $approvedCount,
                    'description' => 'Positive outcomes worth following up on.',
                ],
                [
                    'label' => 'Rejected',
                    'value' => $rejectedCount,
                    'description' => 'Closed loops you can use to recalibrate search focus.',
                ],
            ],
            'recentApplications' => (clone $applications)
                ->with(['jobPosting.companyUser'])
                ->latest()
                ->take(4)
                ->get(),
            'recommendedJobs' => JobPosting::query()
                ->with('companyUser')
                ->latest()
                ->take(3)
                ->get(),
            'twoFactorEnabled' => (bool) $user->two_factor_confirmed_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function companyPayload(User $user): array
    {
        $jobPostings = JobPosting::query()->byCompany($user->id);
        $applications = Application::query()->forCompanyJobs($user->id);

        $activePostingsCount = (clone $jobPostings)->count();
        $inboundApplicationsCount = (clone $applications)->count();
        $pendingApplicationsCount = (clone $applications)
            ->where('status', ApplicationStatus::PENDING->value)
            ->count();

        return [
            'pageTitle' => 'Hiring Dashboard',
            'homeSurface' => 'company',
            'summaryCards' => [
                [
                    'label' => 'Active Listings',
                    'value' => number_format($activePostingsCount),
                    'description' => 'Current hiring surfaces owned by your team.',
                ],
                [
                    'label' => 'Inbound Applications',
                    'value' => number_format($inboundApplicationsCount),
                    'description' => 'Candidates currently inside your response queue.',
                ],
                [
                    'label' => 'Awaiting Review',
                    'value' => number_format($pendingApplicationsCount),
                    'description' => 'Applications still waiting on a decision.',
                ],
            ],
            'responseQueue' => (clone $applications)
                ->with(['jobPosting', 'applicantUser'])
                ->latest()
                ->take(4)
                ->get(),
            'activeListings' => (clone $jobPostings)
                ->withCount('applications')
                ->latest()
                ->take(4)
                ->get(),
            'operationalChecklist' => $this->companyChecklist(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function companyChecklist(): array
    {
        return [
            [
                'title' => 'Publish or revise a listing',
                'description' => 'Keep your open roles clear, current, and discoverable.',
                'href' => route('jobs.create'),
                'action' => 'Open composer',
            ],
            [
                'title' => 'Review inbound candidates',
                'description' => 'Move pending applicants forward before the queue goes stale.',
                'href' => route('my.applications.index'),
                'action' => 'Open applications',
            ],
            [
                'title' => 'Refresh account posture',
                'description' => 'Update profile details and confirm security settings stay current.',
                'href' => route('profile.show'),
                'action' => 'Open profile',
            ],
        ];
    }
}
