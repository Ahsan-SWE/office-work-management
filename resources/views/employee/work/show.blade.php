@extends('layouts.role')

@section('page-title', $assignment->assignment_code)
@section('page-subtitle', $assignment->workOrder->client->name.' · '.$assignment->workOrder->work_code)

@section('content')
<div class="mx-auto max-w-6xl">
    <a href="{{ route('employee.work.index') }}" class="text-sm font-semibold text-slate-500">← My Work</a>

    <div class="mt-4 grid gap-5 lg:grid-cols-[1fr_380px]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold">{{ $assignment->workOrder->work_type->label() }}</h2>
                        @if($assignment->workOrder->title)
                            <p class="mt-1 font-semibold">{{ $assignment->workOrder->title }}</p>
                        @endif
                    </div>
                    <div class="flex gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $assignment->status->value }}</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $assignment->priority->value }}</span>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><p class="text-slate-500">Client</p><p class="font-semibold">{{ $assignment->workOrder->client->name }}</p></div>
                    <div><p class="text-slate-500">Section</p><p class="font-semibold">{{ $assignment->section?->name ?? 'Custom' }}</p></div>
                    <div><p class="text-slate-500">Scope Type</p><p class="font-semibold">{{ str_replace('_', ' ', $assignment->scope_type->value) }}</p></div>
                    <div><p class="text-slate-500">Assigned Count</p><p class="font-semibold">{{ $assignment->assigned_count ?? 'Count-less job' }}</p></div>
                </div>

                @if($assignment->scope_text)
                    <div class="mt-5 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase text-slate-500">Scope</p>
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $assignment->scope_text }}</p>
                    </div>
                @endif

                @if($assignment->instruction)
                    <div class="mt-5 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase text-slate-500">Instruction</p>
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $assignment->instruction }}</p>
                    </div>
                @endif

                <div class="mt-5 grid gap-3">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-xs font-semibold uppercase text-blue-700">Current Client Sheet</p>
                        <p class="mt-1 text-sm text-slate-600">Use the latest Client Sheet for active work.</p>
                        <a href="{{ $assignment->workOrder->client->google_sheet_url }}" target="_blank" rel="noopener" class="mt-2 block break-all font-semibold text-blue-700">Open Current Google Sheet ↗</a>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase text-slate-500">Created With Sheet</p>
                        <p class="mt-1 text-sm text-slate-500">Historical snapshot from when the Work Order was created.</p>
                        <a href="{{ $assignment->workOrder->sheet_url_snapshot }}" target="_blank" rel="noopener" class="mt-2 block break-all font-semibold text-slate-700">Open Historical Snapshot ↗</a>
                    </div>

                    @if($assignment->workOrder->client->google_sheet_url !== $assignment->workOrder->sheet_url_snapshot)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            The Client Sheet has changed. Continue active work from <strong>Current Client Sheet</strong>; the older link is history only.
                        </div>
                    @endif
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">QC status</h2>
                        <p class="mt-1 text-sm text-slate-500">Approved work is locked and cannot be resubmitted.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">QC Total</p>
                        <p class="mt-1 text-lg font-bold">{{ $qcSummary['effective_total'] }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Approved Locked</p>
                        <p class="mt-1 text-lg font-bold">{{ $qcSummary['approved_total'] }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Original Submitted</p>
                        <p class="mt-1 text-lg font-bold">{{ $qcSummary['original_submitted'] }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Available to Submit</p>
                        <p class="mt-1 text-lg font-bold">{{ $qcSummary['available_to_submit'] }}</p>
                    </div>
                    <div class="rounded-xl {{ $qcSummary['unresolved_rework'] > 0 ? 'bg-red-50' : 'bg-slate-50' }} p-3">
                        <p class="text-xs {{ $qcSummary['unresolved_rework'] > 0 ? 'text-red-600' : 'text-slate-500' }}">Unresolved Rework</p>
                        <p class="mt-1 text-lg font-bold">{{ $qcSummary['unresolved_rework'] }}</p>
                    </div>
                </div>
            </section>

            @if($qcSummary['unresolved_reviews']->isNotEmpty())
                <section class="rounded-2xl border border-red-200 bg-white p-6 shadow-sm">
                    <h2 class="font-bold text-red-700">Rework required</h2>
                    <p class="mt-1 text-sm text-slate-500">Fix each returned QC review and resubmit its full returned count together.</p>

                    <div class="mt-4 space-y-4">
                        @foreach($qcSummary['unresolved_reviews'] as $review)
                            <div class="rounded-xl border border-red-200 bg-red-50/40 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold">{{ $review->review_code }}</p>
                                        <p class="text-sm text-slate-600">{{ $review->submission->submission_code }} · {{ $review->rework_count }} item(s) returned</p>
                                    </div>
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">REWORK</span>
                                </div>

                                @if($review->issues->isNotEmpty())
                                    <div class="mt-3 space-y-2">
                                        @foreach($review->issues as $issue)
                                            <div class="rounded-lg border border-red-100 bg-white px-3 py-2 text-sm">
                                                <p class="font-semibold">{{ $issue->reason->name }} · -{{ $issue->negative_points }}</p>
                                                @if($issue->site_name)<p class="text-xs text-slate-500">Site/account: {{ $issue->site_name }}</p>@endif
                                                @if($issue->comment)<p class="mt-1 text-slate-600">{{ $issue->comment }}</p>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if($review->review_comment)
                                    <p class="mt-3 text-sm text-slate-600"><strong>QC comment:</strong> {{ $review->review_comment }}</p>
                                @endif

                                <form method="POST" action="{{ route('employee.work.qc-rework', [$assignment, $review]) }}" class="mt-4 grid gap-3">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                                    <div>
                                        <label class="text-sm font-semibold">Rework note</label>
                                        <textarea name="employee_note" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="What was corrected?"></textarea>
                                    </div>
                                    <button class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white">
                                        Resubmit {{ $review->rework_count }} Rework Item(s)
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">QC history</h2>

                <div class="mt-4 space-y-3">
                    @forelse($assignment->qcSubmissions as $submission)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ $submission->submission_code }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $submission->submission_type->value }} · {{ $submission->submitted_count }} item(s) · {{ $submission->submitted_at?->format('Y-m-d H:i') }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $submission->status->value }}</span>
                            </div>

                            @if($submission->review)
                                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                                    <p class="font-semibold">
                                        {{ $submission->review->review_code }}
                                        @if($submission->review->result)
                                            · {{ $submission->review->result->value }}
                                        @endif
                                    </p>
                                    @if($submission->review->reviewed_at)
                                        <p class="mt-1 text-slate-600">
                                            Approved {{ $submission->review->approved_count }} ·
                                            Rework {{ $submission->review->rework_count }} ·
                                            Negative {{ $submission->review->negative_points }}/5 ·
                                            Bonus {{ $submission->review->bonus_points }}/5
                                        </p>
                                    @else
                                        <p class="mt-1 text-slate-500">Reviewing by {{ $submission->review->reviewer->name }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No QC submissions yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold">Work action</h2>

                @if($assignment->status === \App\Enums\AssignmentStatus::PENDING)
                    <form method="POST" action="{{ route('employee.work.start', $assignment) }}" class="mt-4">
                        @csrf
                        <button class="w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Start Work</button>
                    </form>
                @elseif(in_array($assignment->status, [\App\Enums\AssignmentStatus::ONGOING, \App\Enums\AssignmentStatus::REWORK], true))
                    <p class="mt-2 text-sm text-slate-500">Update progress. Final completion happens only after QC approval.</p>
                    <form method="POST" action="{{ route('employee.work.progress', $assignment) }}" class="mt-4 grid gap-3">
                        @csrf
                        @if($assignment->assigned_count !== null)
                            <div>
                                <label class="text-sm font-semibold">Completed Count</label>
                                <input type="number" name="completed_count" min="{{ $assignment->completed_count }}" max="{{ $assignment->assigned_count }}" value="{{ $assignment->completed_count }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                                <p class="mt-1 text-xs text-slate-500">Assigned: {{ $assignment->assigned_count }}</p>
                            </div>
                        @endif
                        <div>
                            <label class="text-sm font-semibold">Progress Note</label>
                            <textarea name="note" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                        </div>
                        <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold">Save Progress</button>
                    </form>
                @elseif(in_array($assignment->status, [\App\Enums\AssignmentStatus::SUBMITTED_QC, \App\Enums\AssignmentStatus::QC_REVIEWING], true))
                    <div class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-800">
                        All current original scope is submitted. Wait for QC or continue from a returned rework if one appears.
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-500">This assignment is read-only.</p>
                @endif
            </section>

            @if(
                $assignment->status === \App\Enums\AssignmentStatus::ONGOING
                && $qcSummary['available_to_submit'] > 0
                && auth()->user()->can('task.submit_qc')
            )
                <section class="rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold">Submit for QC</h2>
                    <p class="mt-1 text-sm text-slate-500">PARTIAL / FINAL is selected automatically by the system.</p>

                    <form method="POST" action="{{ route('employee.work.qc-submit', $assignment) }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

                        @if($assignment->assigned_count !== null)
                            <div>
                                <label class="text-sm font-semibold">Submit Count</label>
                                <input
                                    type="number"
                                    name="submitted_count"
                                    min="1"
                                    max="{{ $qcSummary['available_to_submit'] }}"
                                    value="{{ old('submitted_count', $qcSummary['available_to_submit']) }}"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                                    required
                                >
                                <p class="mt-1 text-xs text-slate-500">Maximum available now: {{ $qcSummary['available_to_submit'] }}</p>
                            </div>
                        @else
                            <input type="hidden" name="submitted_count" value="1">
                            <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">This count-less Custom Job submits as one QC unit.</div>
                        @endif

                        <div>
                            <label class="text-sm font-semibold">Scope / Area <span class="font-normal text-slate-400">(optional)</span></label>
                            <textarea name="scope_text" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('scope_text') }}</textarea>
                        </div>

                        <div>
                            <label class="text-sm font-semibold">Employee Note <span class="font-normal text-slate-400">(optional)</span></label>
                            <textarea name="employee_note" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('employee_note') }}</textarea>
                        </div>

                        <button class="rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white">Submit for QC</button>
                    </form>
                </section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold">Progress timeline</h2>
                <div class="mt-4 space-y-3">
                    @forelse($assignment->progressLogs as $log)
                        <div class="rounded-lg border border-slate-200 p-3 text-sm">
                            <p class="font-semibold">{{ $log->event_type }} · {{ $log->from_count }} → {{ $log->to_count }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $log->created_at?->format('Y-m-d H:i') }}</p>
                            @if($log->note)<p class="mt-2 text-slate-600">{{ $log->note }}</p>@endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No progress updates yet.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
