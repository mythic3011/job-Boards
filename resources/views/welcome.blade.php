@extends('layouts.app')

@section('title', 'Welcome to ' . config('app.name', 'Jobs Board'))

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Hero Section -->
    <div class="text-center py-16 px-4">
        <h1 class="text-5xl font-bold text-gray-900 mb-4">
            Find Your Dream Job
        </h1>
        <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            Connect with top companies and discover opportunities that match your skills and passion.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    @auth
                <a href="{{ route('jobs.index') }}" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Browse Jobs
                </a>
                @if(auth()->user()->isCompany())
                    <a href="{{ route('jobs.create') }}" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Post a Job
                            </a>
                        @endif
            @else
                <a href="{{ route('jobs.index') }}" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Browse Jobs
                </a>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Get Started
                </a>
            @endauth
        </div>
                </div>

    <!-- Features Section -->
    <div class="py-12 px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-4">
                    <x-heroicon-o-briefcase class="w-8 h-8 text-indigo-600" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">For Job Seekers</h3>
                <p class="text-gray-600">
                    Browse thousands of job opportunities, apply with your CV, and track your applications all in one place.
                </p>
            </div>

            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                    <x-heroicon-o-building-office class="w-8 h-8 text-green-600" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">For Companies</h3>
                <p class="text-gray-600">
                    Post job openings, receive applications, and find the perfect candidates for your team.
                </p>
            </div>

            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-4">
                    <x-heroicon-o-shield-check class="w-8 h-8 text-blue-600" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Secure & Private</h3>
                <p class="text-gray-600">
                    Your data is protected with industry-standard security measures and two-factor authentication.
                </p>
            </div>
        </div>
    </div>

    <!-- Recent Jobs Section -->
    @php
        $recentJobs = \App\Models\JobPosting::latest()->take(6)->get();
    @endphp

    @if($recentJobs->count() > 0)
        <div class="py-12 px-4">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Recent Job Postings</h2>
                <a href="{{ route('jobs.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                    View All →
                </a>
        </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($recentJobs as $job)
                    <div class="bg-white rounded-lg border border-gray-200 p-6 hover:shadow-lg transition-shadow">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">
                            <a href="{{ route('jobs.show', $job->idcode) }}" class="hover:text-indigo-600">
                                {{ $job->title }}
                            </a>
                        </h3>
                        <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                            {{ \Illuminate\Support\Str::limit($job->requirement, 120) }}
                        </p>
                        <div class="flex items-center justify-between text-sm">
                            @if($job->salary)
                                <span class="text-emerald-700 font-medium">{{ $job->salary }}</span>
                            @endif
                            <span class="text-gray-500">{{ $job->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    <!-- CTA Section -->
    <div class="py-16 px-4 bg-indigo-50 rounded-lg my-12">
        <div class="text-center max-w-2xl mx-auto">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
                Ready to Get Started?
            </h2>
            <p class="text-lg text-gray-600 mb-8">
                Join thousands of job seekers and companies already using our platform.
            </p>
            @guest
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Create Account
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Sign In
                    </a>
                </div>
            @endguest
        </div>
    </div>
</div>
@endsection
