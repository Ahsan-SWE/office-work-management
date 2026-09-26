@extends('layouts.role')

@section('page-title', 'Employee Dashboard')
@section('page-subtitle', $employee->primaryTeam?->name ?? 'No team assigned')

@section('content')
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Primary Team</p>
            <p class="mt-2 text-xl font-bold">{{ $employee->primaryTeam?->name ?? '—' }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Status</p>
            <p class="mt-2 text-xl font-bold">{{ $employee->status->value }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Capabilities</p>
            <p class="mt-2 font-bold">
                {{ $employee->capabilities->pluck('capability')->map(fn($v) => $v->value)->join(', ') ?: 'Not configured' }}
            </p>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold">My Work</h2>
        <p class="mt-2 text-sm text-slate-500">
            Assignment queue, Start Work, progress updates and QC submission activate in Milestone 2.
        </p>
    </div>
@endsection
