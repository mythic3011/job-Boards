@extends('layouts.app')

@section('title', 'Welcome to ' . config('app.name', 'Jobs Board'))

@section('content')
<div class="max-w-7xl mx-auto">

    {{-- Hero --}}
    <div class="text-center py-16 px-4">
        <h1 class="text-5xl font-bold text-gray-900 mb-4">
            Find Your Dream Job
        </h1>
        <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto leading-relaxed">
            Connect with top companies and discover opportunities that match your skills and passion.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            @auth
                <a href="{{ route('jobs.index') }}"
                   class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors cursor-pointer">
                    Browse Jobs
                </a>
                @if(auth()->user()->isCompany())
                    <a href="{{ route('jobs.create') }}"
                       class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors cursor-pointer">
                        Post a Job
                    </a>
                @endif
            @else
                <a href="{{ route('jobs.index') }}"
                   class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors cursor-pointer">
                    Browse Jobs
                </a>
                <a href="{{ route('register') }}"
                   class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors cursor-pointer">
                    Get Started
                </a>
            @endauth
        </div>
    </div>

    {{-- Features --}}
    <div class="py-12 px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-4">
                    <x-heroicon-o-briefcase class="w-8 h-8 text-indigo-600" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">For Job Seekers</h3>
                <p class="text-gray-600 leading-relaxed">
                    Browse job opportunities, apply with your CV, and track your applications all in one place.
                </p>
            </div>
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                    <x-heroicon-o-building-office class="w-8 h-8 text-green-600" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">For Companies</h3>
                <p class="text-gray-600 leading-relaxed">
                    Post job openings, receive applications, and find the perfect candidates for your team.
                </p>
            </div>
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-4">
                    <x-heroicon-o-shield-check class="w-8 h-8 text-blue-600" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Secure & Private</h3>
                <p class="text-gray-600 leading-relaxed">
                    Your data is protected with industry-standard security measures and two-factor authentication.
                </p>
            </div>
        </div>
    </div>

    {{-- Recent Jobs --}}
    @php
        $recentJobs = \App\Models\JobPosting::with('companyUser')->latest()->take(6)->get();
    @endphp

    @if($recentJobs->count() > 0)
        <div class="py-12 px-4">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Recent Job Postings</h2>
                <a href="{{ route('jobs.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                    View all &rarr;
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($recentJobs as $job)
                    <a href="{{ route('jobs.show', $job->idcode) }}"
                       class="group block bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-200 hover:shadow-md transition-all duration-150 cursor-pointer">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="flex items-center justify-center w-9 h-9 rounded-md bg-indigo-50 border border-indigo-100 text-indigo-700 font-bold text-xs uppercase shrink-0 group-hover:bg-indigo-100 transition-colors">
                                {{ substr($job->companyUser->nickname ?? '?', 0, 2) }}
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-700 transition-colors leading-snug truncate">
                                    {{ $job->title }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $job->companyUser->nickname }}</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 line-clamp-2 leading-relaxed mb-4">
                            {{ \Illuminate\Support\Str::limit($job->requirement, 120) }}
                        </p>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            @if($job->salary)
                                <span class="font-semibold text-emerald-700">{{ $job->salary }}</span>
                            @else
                                <span></span>
                            @endif
                            <span>{{ $job->created_at->diffForHumans() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- CTA --}}
    @guest
        <div class="py-16 px-4 bg-indigo-50 rounded-2xl border border-indigo-100 my-12">
            <div class="text-center max-w-2xl mx-auto">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Ready to Get Started?</h2>
                <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                    Join job seekers and companies already using our platform.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors cursor-pointer">
                        Create Account
                    </a>
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors cursor-pointer">
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    @endguest

</div>
@endsection
