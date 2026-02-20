<?php

namespace App\Http\Controllers;

use App\Services\JobService;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Store a new job posting (POST /jobs).
     * Used when form is submitted without Livewire or as fallback.
     */
    public function store(Request $request)
    {
        if (!auth()->check() || !auth()->user()->isCompany()) {
            abort(403, 'Only company users can create job postings.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'requirement' => ['required', 'string'],
            'duty' => ['required', 'string'],
            'salary' => ['nullable', 'string', 'max:255', 'regex:/^(?!\s+$)[0-9\s\-,]*$/'],
        ]);

        $validated = [
            'title' => trim($validated['title']),
            'requirement' => trim($validated['requirement']),
            'duty' => trim($validated['duty']),
            'salary' => isset($validated['salary']) ? trim($validated['salary']) : null,
        ];

        if ($validated['salary'] === '') {
            $validated['salary'] = null;
        }

        /** @var JobService $jobService */
        $jobService = app(JobService::class);
        $jobService->createJob($validated);

        return redirect()
            ->route('jobs.index')
            ->with('message', 'Job posting created successfully.');
    }
}
