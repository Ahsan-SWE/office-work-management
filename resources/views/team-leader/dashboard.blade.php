@extends('layouts.role')

@section('page-title', 'Team Leader Dashboard')
@section('page-subtitle', $team ? $team->name : 'No active team assigned')

@section('content')
    @if(!$team)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
            <h2 class="font-bold">No team assigned yet</h2>
            <p class="mt-2 text-sm">A Super Admin must assign you as the Primary Team Leader of an active team.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Employees', $employeeCount],
                ['Active', $activeEmployeeCount],
                ['Inactive', $inactiveEmployeeCount],
                ['Left Company', $leftEmployeeCount],
            ] as [$label, $value])
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">Team capability coverage</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach($capabilityCounts as $name => $count)
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold text-slate-500">{{ $name }}</p>
                            <p class="mt-1 text-2xl font-bold">{{ $count }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">Work management</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Client, assignment, QC and workload cards will activate in Milestone 2.
                </p>
                <a href="{{ route('team-leader.members.index') }}" class="mt-5 inline-block rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">
                    Manage My Team
                </a>
            </section>
        </div>
    @endif
@endsection
