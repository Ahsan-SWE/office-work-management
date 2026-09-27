@extends('layouts.role')

@section('page-title', 'My Performance')
@section('page-subtitle', 'Monthly QC quality totals. Bonus is shown separately and never reduces negative points.')

@section('content')
    <div class="mx-auto max-w-5xl space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('employee.performance.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="month" class="block text-sm font-semibold text-slate-700">Performance month</label>
                    <input
                        id="month"
                        name="month"
                        type="month"
                        value="{{ $selectedMonth->format('Y-m') }}"
                        class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>
                <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">
                    View month
                </button>
            </form>
        </section>

        @if ($performance)
            @php
                $rating = $performance->rating;
                $badge = match ($rating->value) {
                    'GOOD' => 'bg-emerald-100 text-emerald-800',
                    'NEEDS_ATTENTION' => 'bg-amber-100 text-amber-800',
                    'POOR' => 'bg-red-100 text-red-800',
                    default => 'bg-slate-100 text-slate-700',
                };
            @endphp
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-slate-500">{{ $selectedMonth->format('F Y') }}</p>
                        <h2 class="mt-1 text-xl font-bold">Monthly Quality</h2>
                    </div>
                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $badge }}">
                        {{ $rating->label() }}
                    </span>
                </div>

                <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <dt class="text-sm text-slate-500">Completed QC Reviews</dt>
                        <dd class="mt-1 text-2xl font-bold">{{ $performance->review_count }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <dt class="text-sm text-slate-500">Negative</dt>
                        <dd class="mt-1 text-2xl font-bold">{{ $performance->negative_points }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <dt class="text-sm text-slate-500">Bonus</dt>
                        <dd class="mt-1 text-2xl font-bold">{{ $performance->bonus_points }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-sm text-slate-500">
                    Quality status is based on negative points only. Bonus points never offset negative points.
                </p>
            </section>
        @else
            <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
                <h2 class="font-semibold">No QC Data</h2>
                <p class="mt-2 text-sm text-slate-500">
                    There are no completed QC reviews for {{ $selectedMonth->format('F Y') }}.
                </p>
            </section>
        @endif
    </div>
@endsection
