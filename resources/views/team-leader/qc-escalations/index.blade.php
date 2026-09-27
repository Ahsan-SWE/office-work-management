@extends('layouts.role')

@section('page-title', 'QC Appeals')
@section('page-subtitle', 'Review completed QC decisions for your team and escalate an appeal to Super Admin.')

@section('content')
<div class="mx-auto max-w-6xl">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Review</th>
                        <th class="px-3 py-2">Employee</th>
                        <th class="px-3 py-2">Work</th>
                        <th class="px-3 py-2">Effective decision</th>
                        <th class="px-3 py-2">Appeal</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $review)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-3">
                                <p class="font-semibold">{{ $review->review_code }}</p>
                                <p class="text-xs text-slate-500">{{ $review->reviewed_at?->format('Y-m-d H:i') }}</p>
                            </td>
                            <td class="px-3 py-3">{{ $review->responsibleEmployee->name }}</td>
                            <td class="px-3 py-3">
                                {{ $review->submission->assignment->workOrder->work_code }}
                                <p class="text-xs text-slate-500">{{ $review->submission->assignment->workOrder->client->name }}</p>
                            </td>
                            <td class="px-3 py-3">
                                {{ $review->effectiveResult()?->value }}
                                · A {{ $review->effectiveApprovedCount() }}
                                · R {{ $review->effectiveReworkCount() }}
                                · N {{ $review->effectiveNegativePoints() }}/5
                                @if($review->effectiveIsMajorError())
                                    <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-700">MAJOR</span>
                                @endif
                                @if($review->latestOverride)
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">OVERRIDDEN</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                {{ $review->escalation?->status ?? 'NOT APPEALED' }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('team-leader.qc-reviews.show', $review) }}" class="font-semibold text-blue-700">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">No completed QC reviews for your team yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $reviews->links() }}</div>
    </section>
</div>
@endsection
