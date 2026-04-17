@extends('layouts.app')

@section('title', $pageTitle ?? ('Welcome to ' . config('app.name', 'Jobs Board')))

@section('content')
<div class="mx-auto max-w-7xl">
    @includeWhen(($homeSurface ?? 'guest') === 'guest', 'home.partials.guest', ['recentJobs' => $recentJobs ?? collect()])
    @includeWhen(($homeSurface ?? null) === 'individual', 'home.partials.individual')
    @includeWhen(($homeSurface ?? null) === 'company', 'home.partials.company')
    @includeWhen(($homeSurface ?? null) === 'admin', 'home.partials.admin')
</div>
@endsection
