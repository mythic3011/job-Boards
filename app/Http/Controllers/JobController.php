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
            'salary_from' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'salary_to' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ]);

        $salaryFrom = $validated['salary_from'] ?? null;
        $salaryTo = $validated['salary_to'] ?? null;

        if ($salaryTo && $salaryFrom && $salaryTo <= $salaryFrom) {
            return back()->withErrors(['salary_to' => 'The upper salary must be greater than the lower salary.'])->withInput();
        }

        $jobService = app(JobService::class);
        $jobService->createJob([
            'title' => trim($validated['title']),
            'requirement' => trim($validated['requirement']),
            'duty' => trim($validated['duty']),
            'salary_from' => $salaryFrom,
            'salary_to' => $salaryTo,
        ]);

        return redirect()
            ->route('jobs.index')
            ->with('message', 'Job posting created successfully.');
    }
}
