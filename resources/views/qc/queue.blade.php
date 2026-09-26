@extends('layouts.role')

@section('page-title', 'QC Queue')
@section('page-subtitle', 'Scope-based review queue with rework and review locking')

@section('content')
<div class="mx-auto max-w-7xl">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold">Active QC scopes</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $scopes->isNotEmpty() ? $scopes->join(', ') : 'No active QC scope assigned.' }}
                </p>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            @foreach([
                'waiting' => 'Waiting for QC',
                'reviewing' => 'QC Reviewing',
                'rework' => 'Rework Resubmitted',
                'reviewed' => 'Reviewed',
            ] as $key => $label)
                <a
                    href="{{ route('qc.queue', ['tab' => $key]) }}"
                    class="rounded-lg px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Submission</th>
                        <th class="px-4 py-3">Client / Work</th>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Scope</th>
                        <th class="px-4 py-3">Count</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Waiting</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($submissions as $submission)
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <p class="font-bold">{{ $submission->submission_code }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $submission->submission_type->value }} · {{ $submission->status->value }}</p>
                                @if($submission->sourceReview)
                                    <p class="mt-1 text-xs text-red-600">From {{ $submission->sourceReview->review_code }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold">{{ $submission->assignment->workOrder->client->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $submission->assignment->workOrder->work_code }} · {{ $submission->assignment->assignment_code }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold">{{ $submission->assignment->employee->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $submission->assignment->employee->email }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold">{{ $submission->assignment->workOrder->work_type->value }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $submission->assignment->section?->name ?? 'Custom' }}</p>
                            </td>
                            <td class="px-4 py-4 font-semibold">{{ $submission->submitted_count }}</td>
                            <td class="px-4 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $submission->assignment->priority->value }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <p>{{ $submission->submitted_at?->diffForHumans() }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $submission->submitted_at?->format('Y-m-d H:i') }}</p>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('qc.submissions.show', $submission) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">No submissions in this queue.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($submissions->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
