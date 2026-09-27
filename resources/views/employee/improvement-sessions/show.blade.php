@extends('layouts.role')

@section('page-title', 'Improvement Session')
@section('page-subtitle', $session->performance_month->format('F Y').' monthly quality follow-up.')

@section('content')
<div class="grid gap-5 xl:grid-cols-[1fr_360px]">
    <div class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Trigger month</p>
                    <h2 class="text-xl font-bold">{{ $session->performance_month->format('F Y') }}</h2>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">
                    {{ $session->status->label() }}
                </span>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Trigger rating</p>
                    <p class="mt-1 font-bold">{{ $session->trigger_rating->label() }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Negative</p>
                    <p class="mt-1 font-bold">{{ $session->trigger_negative_points }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Bonus</p>
                    <p class="mt-1 font-bold">{{ $session->trigger_bonus_points }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold">Improvement plan</h2>
            <div class="mt-3 whitespace-pre-line text-sm text-slate-700">
                {{ $session->improvement_plan ?: 'No improvement plan has been recorded yet.' }}
            </div>

            <h3 class="mt-6 font-semibold">Notes for you</h3>
            <div class="mt-2 whitespace-pre-line text-sm text-slate-700">
                {{ $session->employee_visible_notes ?: 'No employee-visible notes yet.' }}
            </div>

            <p class="mt-6 text-sm text-slate-500">
                Follow-up:
                <strong class="text-slate-700">
                    {{ $session->follow_up_date?->format('M j, Y') ?? 'Not scheduled' }}
                </strong>
            </p>
        </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold">Session history</h2>
        <div class="mt-4 space-y-4">
            @forelse($session->events as $event)
                <div class="border-l-2 border-slate-200 pl-3">
                    <p class="text-sm font-semibold">{{ $event->event_type->label() }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $event->created_at->format('M j, Y g:i A') }}
                        @if($event->actor)
                            · {{ $event->actor->name }}
                        @endif
                    </p>
                    @if($event->note)
                        <p class="mt-1 text-sm text-slate-600">{{ $event->note }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">No history yet.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
