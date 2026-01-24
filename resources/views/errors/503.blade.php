@extends('layouts.app')

@section('title', '503 - Service Unavailable')

@section('content')
<div class="flex min-h-[60vh] flex-col items-center justify-center text-center">
    <div class="max-w-md">
        <h1 class="text-6xl font-bold text-gray-900">503</h1>
        <h2 class="mt-4 text-2xl font-semibold text-gray-800">Service Unavailable</h2>
        <p class="mt-4 text-gray-600">
            We're temporarily down for maintenance. Please check back soon.
        </p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="javascript:location.reload()" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Refresh Page
            </a>
            <a href="{{ route('jobs.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Go to Jobs
            </a>
        </div>
    </div>
</div>
@endsection
