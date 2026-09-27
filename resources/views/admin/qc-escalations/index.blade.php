@extends('layouts.admin')

@section('page-title', 'QC Escalations')
@section('page-subtitle', 'Team Leader appeals requiring Super Admin review.')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach(['OPEN', 'RESOLVED', 'ALL'] as $tab)
            <a href="{{ route('admin.qc-escalations.index', ['status' => $tab]) }}"
               class="rounded-lg px-3 py-2 font-semibold {{ $status === $tab ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white' }}">
                {{ $tab }}
            </a>
        @endforeach
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Review</th>
                        <th class="px-3 py-2">Employee</th>
                        <th class="px-3 py-2">Raised by</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Opened</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($escalations as $escalation)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-3">
                                <p class="font-semibold">{{ $escalation->review->review_code }}</p>
                                <p class="text-xs text-slate-500">{{ $escalation->review->submission->assignment->workOrder->work_code }}</p>
                            </td>
                            <td class="px-3 py-3">{{ $escalation->review->responsibleEmployee->name }}</td>
                            <td class="px-3 py-3">{{ $escalation->raiser->name }}</td>
                            <td class="px-3 py-3">{{ $escalation->status }}</td>
                            <td class="px-3 py-3">{{ $escalation->opened_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-3 text-right"><a href="{{ route('admin.qc-escalations.show', $escalation) }}" class="font-semibold text-blue-700">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">No QC escalations in this view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $escalations->links() }}</div>
    </section>
</div>
@endsection
