@extends('layouts.role')

@section('page-title', $review->review_code)
@section('page-subtitle', 'QC appeal details · original QC record remains immutable.')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <a href="{{ route('team-leader.qc-reviews.index') }}" class="text-sm font-semibold text-slate-500">← QC Appeals</a>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ $review->review_code }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $review->responsibleEmployee->name }} ·
                    {{ $review->submission->assignment->workOrder->work_code }} /
                    {{ $review->submission->assignment->assignment_code }}
                </p>
            </div>
            @if($review->effectiveIsMajorError())
                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">MAJOR ERROR</span>
            @endif
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-5">
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Result</p><p class="font-bold">{{ $review->effectiveResult()?->value }}</p></div>
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Approved</p><p class="font-bold">{{ $review->effectiveApprovedCount() }}</p></div>
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Rework</p><p class="font-bold">{{ $review->effectiveReworkCount() }}</p></div>
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Negative</p><p class="font-bold">{{ $review->effectiveNegativePoints() }}/5</p></div>
            <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Bonus</p><p class="font-bold">{{ $review->effectiveBonusPoints() }}/5</p></div>
        </div>

        @if($review->latestOverride)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                A Super Admin override is active. Original QC values below remain unchanged in audit history.
            </div>
        @endif

        <div class="mt-5">
            <h3 class="font-bold">Original QC issues</h3>
            <div class="mt-3 space-y-2">
                @forelse($review->issues as $issue)
                    <div class="rounded-lg border border-slate-200 p-3 text-sm">
                        <strong>{{ $issue->reason->name }}</strong> · -{{ $issue->negative_points }}
                        @if($issue->comment)<p class="mt-1 text-slate-600">{{ $issue->comment }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No issue rows.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold">Appeal / escalation</h2>

        @if(!$review->escalation)
            <p class="mt-1 text-sm text-slate-500">Escalation goes to Super Admin. It does not modify the original QC review.</p>
            <form method="POST" action="{{ route('team-leader.qc-reviews.appeal', $review) }}" class="mt-4 grid gap-3">
                @csrf
                <textarea name="reason" rows="5" minlength="10" maxlength="5000" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Explain the specific reason for the appeal..." required>{{ old('reason') }}</textarea>
                <button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Escalate to Super Admin</button>
            </form>
        @else
            <div class="mt-3 rounded-xl bg-slate-50 p-4 text-sm">
                <p><strong>Status:</strong> {{ $review->escalation->status }}</p>
                <p class="mt-2 whitespace-pre-line"><strong>Reason:</strong> {{ $review->escalation->reason }}</p>
                @if($review->escalation->resolution_note)
                    <p class="mt-2 whitespace-pre-line"><strong>Resolution:</strong> {{ $review->escalation->resolution_note }}</p>
                @endif
            </div>
        @endif
    </section>

    @if($review->overrides->isNotEmpty())
        <section class="rounded-2xl border border-amber-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold">Super Admin override history</h2>
            <div class="mt-4 space-y-3">
                @foreach($review->overrides->sortBy('created_at') as $override)
                    <div class="rounded-xl bg-amber-50 p-4 text-sm">
                        <p class="font-semibold">{{ $override->created_at?->format('Y-m-d H:i') }} · {{ $override->superAdmin->name }}</p>
                        <p class="mt-1">A {{ $override->approved_count }} · R {{ $override->rework_count }} · N {{ $override->negative_points }}/5 · B {{ $override->bonus_points }}/5 @if($override->is_major_error) · MAJOR ERROR @endif</p>
                        <p class="mt-2 whitespace-pre-line text-amber-900">{{ $override->reason }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
