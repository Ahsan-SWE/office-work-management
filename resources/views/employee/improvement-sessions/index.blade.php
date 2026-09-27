@extends('layouts.role')

@section('page-title', 'Improvement Sessions')
@section('page-subtitle', 'Your monthly quality improvement sessions and follow-up status.')

@section('content')
<div class="space-y-4">
    @forelse($sessions as $session)
        <a href="{{ route('employee.improvement-sessions.show', $session) }}"
           class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">{{ $session->performance_month->format('F Y') }}</p>
                    <h2 class="mt-1 font-bold">Monthly quality improvement</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Trigger: {{ $session->trigger_negative_points }} negative ·
                        {{ $session->trigger_bonus_points }} bonus
                    </p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">
                    {{ $session->status->label() }}
                </span>
            </div>
            @if($session->follow_up_date)
                <p class="mt-3 text-sm text-slate-500">Follow-up: {{ $session->follow_up_date->format('M j, Y') }}</p>
            @endif
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="font-semibold">No Improvement Sessions</p>
            <p class="mt-1 text-sm text-slate-500">No monthly quality session has been opened for you.</p>
        </div>
    @endforelse

    {{ $sessions->links() }}
</div>
@endsection
