@extends(auth()->user()->hasRole('SUPER_ADMIN') ? 'layouts.admin' : 'layouts.role')

@section('page-title',$workOrder->work_code)
@section('page-subtitle',$workOrder->client->name.' · '.$workOrder->work_type->label())

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('work.index') }}" class="text-sm font-semibold text-slate-500">← Work Orders</a>
    <a href="{{ route('clients.show',$workOrder->client) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Open Client</a>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase text-slate-500">Status</p><p class="mt-1 text-lg font-bold">{{ $workOrder->status->value }}</p></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase text-slate-500">Priority</p><p class="mt-1 text-lg font-bold">{{ $workOrder->priority->value }}</p></div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase text-slate-500">Assignments</p><p class="mt-1 text-lg font-bold">{{ $workOrder->assignments->count() }}</p></div>
</div>

<section class="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <h2 class="font-bold">Work details</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div><dt class="text-slate-500">Client</dt><dd class="font-semibold">{{ $workOrder->client->name }} · {{ $workOrder->client->client_code }}</dd></div>
                <div><dt class="text-slate-500">Work Type</dt><dd class="font-semibold">{{ $workOrder->work_type->label() }}</dd></div>
                @if($workOrder->title)<div><dt class="text-slate-500">Title</dt><dd class="font-semibold">{{ $workOrder->title }}</dd></div>@endif
                <div><dt class="text-slate-500">Created by</dt><dd class="font-semibold">{{ $workOrder->creator->name }}</dd></div>
                <div><dt class="text-slate-500">Proof Required</dt><dd class="font-semibold">{{ $workOrder->proof_required ? 'YES':'NO' }}</dd></div>
            </dl>
        </div>
        <div>
            <h2 class="font-bold">Google Sheets</h2>
            <div class="mt-4 grid gap-3">
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-xs font-semibold uppercase text-blue-700">Current Client Sheet</p>
                    <p class="mt-1 text-sm text-slate-600">Use this latest Client Sheet for active work.</p>
                    <a href="{{ $workOrder->client->google_sheet_url }}" target="_blank" rel="noopener" class="mt-2 block break-all font-semibold text-blue-700">{{ $workOrder->client->google_sheet_url }} ↗</a>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase text-slate-500">Created With Sheet</p>
                    <p class="mt-1 text-sm text-slate-500">Historical snapshot from when this Work Order was created.</p>
                    <a href="{{ $workOrder->sheet_url_snapshot }}" target="_blank" rel="noopener" class="mt-2 block break-all font-semibold text-slate-700">{{ $workOrder->sheet_url_snapshot }} ↗</a>
                </div>

                @if($workOrder->client->google_sheet_url !== $workOrder->sheet_url_snapshot)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        The Client Sheet has changed since this Work Order was created. Use <strong>Current Client Sheet</strong> for active work; the old link is kept only for history/audit.
                    </div>
                @endif
            </div>
        </div>
    </div>
    @if($workOrder->instruction)
        <div class="mt-5 rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase text-slate-500">Instruction</p><p class="mt-2 whitespace-pre-line text-sm">{{ $workOrder->instruction }}</p></div>
    @endif
</section>

<section class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-6 py-4"><h2 class="font-bold">Assignments</h2></div>
    <div class="divide-y divide-slate-100">
        @foreach($workOrder->assignments as $assignment)
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><p class="font-bold">{{ $assignment->assignment_code }}</p><p class="mt-1 text-sm text-slate-600">{{ $assignment->employee->name }} · {{ $assignment->employee->email }}</p></div>
                    <div class="flex gap-2 text-xs font-semibold"><span class="rounded-full bg-slate-100 px-3 py-1">{{ $assignment->status->value }}</span><span class="rounded-full bg-slate-100 px-3 py-1">{{ $assignment->priority->value }}</span></div>
                </div>
                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><p class="text-slate-500">Section</p><p class="font-semibold">{{ $assignment->section?->name ?? 'Custom' }}</p></div>
                    <div><p class="text-slate-500">Scope</p><p class="font-semibold">{{ str_replace('_',' ',$assignment->scope_type->value) }}</p></div>
                    <div><p class="text-slate-500">Assigned</p><p class="font-semibold">{{ $assignment->assigned_count ?? '—' }}</p></div>
                    <div><p class="text-slate-500">Progress</p><p class="font-semibold">{{ $assignment->completed_count }}{{ $assignment->assigned_count!==null ? ' / '.$assignment->assigned_count:'' }}</p></div>
                </div>
                @if($assignment->scope_text)<div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm"><strong>Scope:</strong> <span class="whitespace-pre-line">{{ $assignment->scope_text }}</span></div>@endif
                @if($assignment->progressLogs->isNotEmpty())
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase text-slate-500">Progress timeline</p>
                        <div class="mt-2 space-y-2 text-sm">
                            @foreach($assignment->progressLogs->take(5) as $log)
                                <div class="rounded-lg border border-slate-200 px-3 py-2"><strong>{{ $log->event_type }}</strong> · {{ $log->from_count }} → {{ $log->to_count }} · {{ $log->created_at?->format('Y-m-d H:i') }} @if($log->note)<span class="mt-1 block text-slate-600">{{ $log->note }}</span>@endif</div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endsection
