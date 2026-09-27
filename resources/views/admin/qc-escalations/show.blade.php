@extends('layouts.admin')

@section('page-title', 'QC Escalation')
@section('page-subtitle', $escalation->review->review_code.' · original QC review is immutable.')

@section('content')
@php($review = $escalation->review)
<div class="mx-auto max-w-5xl space-y-5">
    <a href="{{ route('admin.qc-escalations.index') }}" class="text-sm font-semibold text-slate-500">← QC Escalations</a>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ $review->review_code }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $review->responsibleEmployee->name }} · reviewer {{ $review->reviewer->name }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $escalation->status === 'OPEN' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">{{ $escalation->status }}</span>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-5">
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Effective</p><p class="font-bold">{{ $review->effectiveResult()?->value }}</p></div>
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Approved</p><p class="font-bold">{{ $review->effectiveApprovedCount() }}</p></div>
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Rework</p><p class="font-bold">{{ $review->effectiveReworkCount() }}</p></div>
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Negative</p><p class="font-bold">{{ $review->effectiveNegativePoints() }}/5</p></div>
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Bonus</p><p class="font-bold">{{ $review->effectiveBonusPoints() }}/5</p></div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-4 text-sm">
                <h3 class="font-bold">Original QC values</h3>
                <p class="mt-2">Result: {{ $review->result?->value }}</p>
                <p>Approved: {{ $review->approved_count }}</p>
                <p>Rework: {{ $review->rework_count }}</p>
                <p>Negative: {{ $review->negative_points }}/5</p>
                <p>Bonus: {{ $review->bonus_points }}/5</p>
                <p>Major Error: {{ $review->is_major_error ? 'YES' : 'NO' }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm">
                <h3 class="font-bold text-red-800">Appeal from {{ $escalation->raiser->name }}</h3>
                <p class="mt-2 whitespace-pre-line text-red-900">{{ $escalation->reason }}</p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-amber-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold">Record a Super Admin override</h2>
        <p class="mt-1 text-sm text-slate-500">Creates a new history row; it never edits the original QC review. Approved + Rework must equal {{ $review->submission->submitted_count }}.</p>

        @if($escalation->status === 'OPEN')
            <form method="POST" action="{{ route('admin.qc-escalations.override', $escalation) }}" class="mt-4 grid gap-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label class="text-sm font-semibold">Approved</label><input type="number" min="0" max="{{ $review->submission->submitted_count }}" name="approved_count" value="{{ old('approved_count', $review->effectiveApprovedCount()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required></div>
                    <div><label class="text-sm font-semibold">Rework</label><input type="number" min="0" max="{{ $review->submission->submitted_count }}" name="rework_count" value="{{ old('rework_count', $review->effectiveReworkCount()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required></div>
                    <div><label class="text-sm font-semibold">Negative 0–5</label><input type="number" min="0" max="5" name="negative_points" value="{{ old('negative_points', $review->effectiveNegativePoints()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required></div>
                    <div><label class="text-sm font-semibold">Bonus 0–5</label><input type="number" min="0" max="5" name="bonus_points" value="{{ old('bonus_points', $review->effectiveBonusPoints()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required></div>
                </div>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="hidden" name="is_major_error" value="0">
                    <input type="checkbox" name="is_major_error" value="1" @checked(old('is_major_error', $review->effectiveIsMajorError()))>
                    Effective Major Error
                </label>
                <textarea name="reason" rows="4" minlength="10" maxlength="5000" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Reason for override..." required>{{ old('reason') }}</textarea>
                <button class="rounded-lg bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white">Record Override</button>
            </form>
        @else
            <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                Resolved escalation is read-only. Existing override history remains available below.
            </div>
        @endif

        @if($review->overrides->isNotEmpty())
            <div class="mt-6 space-y-3">
                <h3 class="font-bold">Override history</h3>
                @foreach($review->overrides->sortByDesc('created_at') as $override)
                    <div class="rounded-xl bg-amber-50 p-4 text-sm">
                        <p class="font-semibold">{{ $override->created_at?->format('Y-m-d H:i') }} · {{ $override->superAdmin->name }}</p>
                        <p class="mt-1">A {{ $override->approved_count }} · R {{ $override->rework_count }} · N {{ $override->negative_points }}/5 · B {{ $override->bonus_points }}/5 @if($override->is_major_error) · MAJOR ERROR @endif</p>
                        <p class="mt-2 whitespace-pre-line">{{ $override->reason }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold">Resolve appeal</h2>
        @if($escalation->status === 'OPEN')
            <form method="POST" action="{{ route('admin.qc-escalations.resolve', $escalation) }}" class="mt-4 grid gap-3">
                @csrf
                <textarea name="resolution_note" rows="4" minlength="5" maxlength="5000" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Resolution note..." required>{{ old('resolution_note') }}</textarea>
                <button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Resolve Escalation</button>
            </form>
        @else
            <div class="mt-3 rounded-xl bg-slate-50 p-4 text-sm whitespace-pre-line">{{ $escalation->resolution_note }}</div>
        @endif
    </section>
</div>
@endsection
