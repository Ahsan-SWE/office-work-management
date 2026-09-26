@extends('layouts.role')
@section('page-title',$assignment->assignment_code)
@section('page-subtitle',$assignment->workOrder->client->name.' · '.$assignment->workOrder->work_code)
@section('content')
<div class="mx-auto max-w-5xl">
    <a href="{{ route('employee.work.index') }}" class="text-sm font-semibold text-slate-500">← My Work</a>
    <div class="mt-4 grid gap-5 lg:grid-cols-[1fr_360px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h2 class="text-xl font-bold">{{ $assignment->workOrder->work_type->label() }}</h2>@if($assignment->workOrder->title)<p class="mt-1 font-semibold">{{ $assignment->workOrder->title }}</p>@endif</div>
                <div class="flex gap-2 text-xs font-semibold"><span class="rounded-full bg-slate-100 px-3 py-1">{{ $assignment->status->value }}</span><span class="rounded-full bg-slate-100 px-3 py-1">{{ $assignment->priority->value }}</span></div>
            </div>
            <div class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                <div><p class="text-slate-500">Client</p><p class="font-semibold">{{ $assignment->workOrder->client->name }}</p></div>
                <div><p class="text-slate-500">Section</p><p class="font-semibold">{{ $assignment->section?->name ?? 'Custom' }}</p></div>
                <div><p class="text-slate-500">Scope Type</p><p class="font-semibold">{{ str_replace('_',' ',$assignment->scope_type->value) }}</p></div>
                <div><p class="text-slate-500">Assigned Count</p><p class="font-semibold">{{ $assignment->assigned_count ?? '—' }}</p></div>
            </div>
            @if($assignment->scope_text)<div class="mt-5 rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase text-slate-500">Scope</p><p class="mt-2 whitespace-pre-line text-sm">{{ $assignment->scope_text }}</p></div>@endif
            @if($assignment->instruction)<div class="mt-5 rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase text-slate-500">Instruction</p><p class="mt-2 whitespace-pre-line text-sm">{{ $assignment->instruction }}</p></div>@endif
            <div class="mt-5 grid gap-3">
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-xs font-semibold uppercase text-blue-700">Current Client Sheet</p>
                    <p class="mt-1 text-xs text-slate-600">Use the latest Client Sheet for active work.</p>
                    <a href="{{ $assignment->workOrder->client->google_sheet_url }}" target="_blank" rel="noopener" class="mt-2 block font-semibold text-blue-700">Open Current Google Sheet ↗</a>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase text-slate-500">Created With Sheet</p>
                    <p class="mt-1 text-xs text-slate-500">Original Sheet snapshot when this assignment was created.</p>
                    <a href="{{ $assignment->workOrder->sheet_url_snapshot }}" target="_blank" rel="noopener" class="mt-2 block font-semibold text-slate-700">Open Historical Snapshot ↗</a>
                </div>

                @if($assignment->workOrder->client->google_sheet_url !== $assignment->workOrder->sheet_url_snapshot)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                        The Client Sheet has changed. Continue active work from <strong>Current Client Sheet</strong>; the older link is history only.
                    </div>
                @endif
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold">Work action</h2>
                @if($assignment->status===\App\Enums\AssignmentStatus::PENDING)
                    <form method="POST" action="{{ route('employee.work.start',$assignment) }}" class="mt-4">@csrf<button class="w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Start Work</button></form>
                @elseif(in_array($assignment->status,[\App\Enums\AssignmentStatus::ONGOING,\App\Enums\AssignmentStatus::REWORK],true))
                    <p class="mt-2 text-sm text-slate-500">Update progress. Final completion is only after QC approval.</p>
                    <form method="POST" action="{{ route('employee.work.progress',$assignment) }}" class="mt-4 grid gap-3">@csrf
                        @if($assignment->assigned_count!==null)
                            <div><label class="text-sm font-semibold">Completed Count</label><input type="number" name="completed_count" min="{{ $assignment->completed_count }}" max="{{ $assignment->assigned_count }}" value="{{ $assignment->completed_count }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><p class="mt-1 text-xs text-slate-500">Assigned: {{ $assignment->assigned_count }}</p></div>
                        @endif
                        <div><label class="text-sm font-semibold">Progress Note</label><textarea name="note" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></div>
                        <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold">Save Progress</button>
                    </form>
                    <div class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">Submit for QC / Partial QC activates in Milestone 3.</div>
                @else
                    <p class="mt-3 text-sm text-slate-500">This assignment is read-only.</p>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold">Progress timeline</h2>
                <div class="mt-4 space-y-3">
                    @forelse($assignment->progressLogs as $log)
                        <div class="rounded-lg border border-slate-200 p-3 text-sm"><p class="font-semibold">{{ $log->event_type }} · {{ $log->from_count }} → {{ $log->to_count }}</p><p class="mt-1 text-xs text-slate-500">{{ $log->created_at?->format('Y-m-d H:i') }}</p>@if($log->note)<p class="mt-2 text-slate-600">{{ $log->note }}</p>@endif</div>
                    @empty<p class="text-sm text-slate-500">No progress updates yet.</p>@endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
