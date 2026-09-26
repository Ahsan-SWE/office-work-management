@extends('layouts.role')

@section('page-title', 'QC Dashboard')
@section('page-subtitle', 'Independent quality control')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Active QC Scopes</p>
            <p class="mt-2 text-3xl font-bold">{{ $scopes->count() }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Waiting for QC</p>
            <p class="mt-2 text-3xl font-bold">0</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Rework Resubmitted</p>
            <p class="mt-2 text-3xl font-bold">0</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Reviewed Today</p>
            <p class="mt-2 text-3xl font-bold">0</p>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold">Assigned scopes</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            @forelse($scopes as $scope)
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold">{{ $scope->scope->value }}</span>
            @empty
                <span class="text-sm text-slate-500">No active QC scope assigned.</span>
            @endforelse
        </div>
        <p class="mt-5 text-sm text-slate-500">
            Real QC queue, locks, partial submissions and review workflow activate in Milestone 3.
        </p>
    </div>
@endsection
