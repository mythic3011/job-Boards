@extends('layouts.app')

@section('title', '404 - Not Found')

@section('content')
<div class="flex min-h-[60vh] flex-col items-center justify-center text-center">
    <div class="max-w-md">
        <h1 class="text-6xl font-bold text-gray-900">404</h1>
        <h2 class="mt-4 text-2xl font-semibold text-gray-800">Page Not Found</h2>
        <p class="mt-4 text-gray-600">
            The page you're looking for doesn't exist or has been moved.
        </p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ route('jobs.index') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Go to Jobs
            </a>
            <a href="javascript:history.back()" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Go Back
            </a>
        </div>
    </div>
</div>
@endsection
