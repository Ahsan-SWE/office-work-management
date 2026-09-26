@extends('layouts.role')

@section('page-title', $submission->submission_code)
@section('page-subtitle', $submission->assignment->workOrder->client->name.' · '.$submission->assignment->workOrder->work_code.' · '.$submission->assignment->assignment_code)

@section('content')
<div class="mx-auto max-w-7xl">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('qc.queue') }}" class="text-sm font-semibold text-slate-500">← QC Queue</a>
        <div class="flex gap-2 text-xs font-semibold">
            <span class="rounded-full bg-slate-100 px-3 py-1">{{ $submission->submission_type->value }}</span>
            <span class="rounded-full bg-slate-100 px-3 py-1">{{ $submission->status->value }}</span>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_420px]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">Submission details</h2>

                <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><p class="text-slate-500">Client</p><p class="font-semibold">{{ $submission->assignment->workOrder->client->name }}</p></div>
                    <div><p class="text-slate-500">Employee</p><p class="font-semibold">{{ $submission->assignment->employee->name }}</p></div>
                    <div><p class="text-slate-500">Work Type</p><p class="font-semibold">{{ $submission->assignment->workOrder->work_type->label() }}</p></div>
                    <div><p class="text-slate-500">Section</p><p class="font-semibold">{{ $submission->assignment->section?->name ?? 'Custom' }}</p></div>
                    <div><p class="text-slate-500">Submitted Count</p><p class="font-semibold">{{ $submission->submitted_count }}</p></div>
                    <div><p class="text-slate-500">Priority</p><p class="font-semibold">{{ $submission->assignment->priority->value }}</p></div>
                </div>

                @if($submission->scope_text)
                    <div class="mt-5 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase text-slate-500">Submitted scope / area</p>
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $submission->scope_text }}</p>
                    </div>
                @endif

                @if($submission->employee_note)
                    <div class="mt-4 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase text-slate-500">Employee note</p>
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $submission->employee_note }}</p>
                    </div>
                @endif

                @if($submission->sourceReview)
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm">
                        <p class="font-semibold text-red-700">Rework from {{ $submission->sourceReview->review_code }}</p>
                        @foreach($submission->sourceReview->issues as $issue)
                            <p class="mt-2">{{ $issue->reason->name }} · -{{ $issue->negative_points }} @if($issue->comment)· {{ $issue->comment }}@endif</p>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">Google Sheets</h2>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-xs font-semibold uppercase text-blue-700">Current Client Sheet</p>
                        <p class="mt-1 text-sm text-slate-600">Use this latest Sheet while checking active work.</p>
                        <a href="{{ $submission->assignment->workOrder->client->google_sheet_url }}" target="_blank" rel="noopener" class="mt-2 block break-all font-semibold text-blue-700">Open Current Sheet ↗</a>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase text-slate-500">Created With Sheet</p>
                        <p class="mt-1 text-sm text-slate-500">Immutable Work Order snapshot.</p>
                        <a href="{{ $submission->assignment->workOrder->sheet_url_snapshot }}" target="_blank" rel="noopener" class="mt-2 block break-all font-semibold text-slate-700">Open Snapshot ↗</a>
                    </div>
                </div>

                @if($submission->assignment->workOrder->client->google_sheet_url !== $submission->assignment->workOrder->sheet_url_snapshot)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        The Client Sheet changed after this Work Order was created. Review active work against <strong>Current Client Sheet</strong>.
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">Task QC history</h2>
                <p class="mt-1 text-sm text-slate-500">Only QC history for this assignment is shown. Monthly employee totals are intentionally hidden.</p>

                <div class="mt-4 space-y-3">
                    @foreach($history as $item)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ $item->submission_code }} · {{ $item->submission_type->value }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $item->submitted_count }} item(s) · {{ $item->submitted_at?->format('Y-m-d H:i') }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $item->status->value }}</span>
                            </div>

                            @if($item->review)
                                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                                    <p class="font-semibold">{{ $item->review->review_code }} · {{ $item->review->reviewer->name }}</p>
                                    @if($item->review->reviewed_at)
                                        <p class="mt-1">
                                            {{ $item->review->result->value }} ·
                                            Approved {{ $item->review->approved_count }} ·
                                            Rework {{ $item->review->rework_count }} ·
                                            Negative {{ $item->review->negative_points }}/5 ·
                                            Bonus {{ $item->review->bonus_points }}/5
                                        </p>
                                        @if($item->review->review_comment)
                                            <p class="mt-2 text-slate-600">{{ $item->review->review_comment }}</p>
                                        @endif
                                    @else
                                        <p class="mt-1 text-slate-500">Review in progress.</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside data-qc-scroll-panel class="space-y-5 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto lg:overscroll-contain lg:pr-2">
            @if($submission->status === \App\Enums\QcSubmissionStatus::WAITING)
                <section class="rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold">Start Review</h2>
                    <p class="mt-2 text-sm text-slate-500">Starting creates the review lock. Other QC users will no longer be able to start this submission.</p>
                    <form method="POST" action="{{ route('qc.submissions.start', $submission) }}" class="mt-4">
                        @csrf
                        <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white">Start Review</button>
                    </form>
                </section>
            @elseif($submission->status === \App\Enums\QcSubmissionStatus::REVIEWING && $submission->review?->reviewer_id !== auth()->id())
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <h2 class="font-bold text-amber-900">Locked</h2>
                    <p class="mt-2 text-sm text-amber-800">Reviewing by {{ $submission->review?->reviewer?->name ?? 'another QC reviewer' }}.</p>
                </section>
            @elseif($submission->status === \App\Enums\QcSubmissionStatus::REVIEWED)
                <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <h2 class="font-bold text-emerald-900">Review completed</h2>
                    @if($submission->review)
                        <p class="mt-2 text-sm text-emerald-900">{{ $submission->review->review_code }} · {{ $submission->review->result?->value }}</p>
                        <p class="mt-2 text-sm text-emerald-800">
                            Approved {{ $submission->review->approved_count }} ·
                            Rework {{ $submission->review->rework_count }} ·
                            Negative {{ $submission->review->negative_points }}/5 ·
                            Bonus {{ $submission->review->bonus_points }}/5
                        </p>
                    @endif
                </section>
            @endif

            @if(
                $submission->status === \App\Enums\QcSubmissionStatus::REVIEWING
                && $submission->review?->reviewer_id === auth()->id()
                && $submission->review?->reviewed_at === null
            )
                @php
                    $issueRows = old('issues');

                    if (! is_array($issueRows) || count($issueRows) === 0) {
                        $issueRows = [[
                            'reason_id' => null,
                            'section_id' => null,
                            'negative_points' => 0,
                            'site_name' => null,
                            'comment' => null,
                        ]];
                    }

                    $bonusRows = old('bonus_items');

                    if (! is_array($bonusRows) || count($bonusRows) === 0) {
                        $bonusRows = [[
                            'reason_id' => null,
                            'points' => null,
                            'comment' => null,
                        ]];
                    }
                @endphp

                <form method="POST" action="{{ route('qc.submissions.finish', $submission) }}" class="space-y-5">
                    @csrf

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="font-bold">Review counts</h2>
                        <p class="mt-1 text-sm text-slate-500">Approved + Rework must equal {{ $submission->submitted_count }}.</p>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm font-semibold">Approved</label>
                                <input type="number" name="approved_count" min="0" max="{{ $submission->submitted_count }}" value="{{ old('approved_count', $submission->submitted_count) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required>
                            </div>
                            <div>
                                <label class="text-sm font-semibold">Rework</label>
                                <input type="number" name="rework_count" min="0" max="{{ $submission->submitted_count }}" value="{{ old('rework_count', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="text-sm font-semibold">Review Comment</label>
                            <textarea name="review_comment" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('review_comment') }}</textarea>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="font-bold">Negative / Rework issues</h2>
                                <p class="mt-1 text-sm text-slate-500">Start with one issue. Add more only when needed. Total negative points must stay between 0 and 5. Rework may use a 0-point issue.</p>
                            </div>
                            <button
                                id="add-qc-issue"
                                type="button"
                                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                            >+ Add Issue</button>
                        </div>

                        <div id="qc-issues-list" class="mt-4 space-y-4">
                            @foreach($issueRows as $i => $row)
                                <div data-qc-item="issue" class="rounded-xl border border-slate-200 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p data-qc-item-label class="text-xs font-semibold uppercase text-slate-500">Issue {{ $loop->iteration }}</p>
                                        <button
                                            type="button"
                                            data-remove-qc-item
                                            class="{{ $loop->first ? 'hidden ' : '' }}text-xs font-semibold text-red-600 hover:text-red-800"
                                        >Remove</button>
                                    </div>

                                    <div class="mt-3 grid gap-3">
                                        <select data-qc-field="reason_id" name="issues[{{ $i }}][reason_id]" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                            <option value="">No issue</option>
                                            @foreach($negativeReasons as $reason)
                                                <option value="{{ $reason->id }}" @selected((string) ($row['reason_id'] ?? '') === (string) $reason->id)>{{ $reason->name }}</option>
                                            @endforeach
                                        </select>

                                        <div class="grid grid-cols-2 gap-3">
                                            <select data-qc-field="section_id" name="issues[{{ $i }}][section_id]" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                                <option value="">Section (optional)</option>
                                                @foreach($sections as $section)
                                                    <option value="{{ $section->id }}" @selected((string) ($row['section_id'] ?? '') === (string) $section->id)>{{ $section->name }}</option>
                                                @endforeach
                                            </select>
                                            <input data-qc-field="negative_points" type="number" name="issues[{{ $i }}][negative_points]" min="0" max="5" value="{{ $row['negative_points'] ?? 0 }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Points 0-5">
                                        </div>

                                        <input data-qc-field="site_name" type="text" name="issues[{{ $i }}][site_name]" value="{{ $row['site_name'] ?? '' }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Site / account (optional)">
                                        <textarea data-qc-field="comment" name="issues[{{ $i }}][comment]" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Issue comment">{{ $row['comment'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="font-bold">Bonus items</h2>
                                <p class="mt-1 text-sm text-slate-500">Start with one bonus item. Add more only when needed. Bonus is separate from negative points and the total must stay between 0 and 5.</p>
                            </div>
                            <button
                                id="add-qc-bonus"
                                type="button"
                                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                            >+ Add Bonus</button>
                        </div>

                        <div id="qc-bonus-list" class="mt-4 space-y-4">
                            @foreach($bonusRows as $i => $row)
                                <div data-qc-item="bonus" class="rounded-xl border border-slate-200 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p data-qc-item-label class="text-xs font-semibold uppercase text-slate-500">Bonus {{ $loop->iteration }}</p>
                                        <button
                                            type="button"
                                            data-remove-qc-item
                                            class="{{ $loop->first ? 'hidden ' : '' }}text-xs font-semibold text-emerald-700 hover:text-emerald-900"
                                        >Remove</button>
                                    </div>

                                    <div class="mt-3 grid gap-3">
                                        <select data-qc-field="reason_id" name="bonus_items[{{ $i }}][reason_id]" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                            <option value="">No bonus</option>
                                            @foreach($bonusReasons as $reason)
                                                <option value="{{ $reason->id }}" @selected((string) ($row['reason_id'] ?? '') === (string) $reason->id)>{{ $reason->name }}</option>
                                            @endforeach
                                        </select>
                                        <input data-qc-field="points" type="number" name="bonus_items[{{ $i }}][points]" min="1" max="5" value="{{ $row['points'] ?? '' }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Points 1-5">
                                        <textarea data-qc-field="comment" name="bonus_items[{{ $i }}][comment]" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Bonus comment">{{ $row['comment'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <button class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Finish Review</button>
                </form>

                <template id="qc-issue-template">
                    <div data-qc-item="issue" class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p data-qc-item-label class="text-xs font-semibold uppercase text-slate-500">Issue</p>
                            <button type="button" data-remove-qc-item class="text-xs font-semibold text-red-600 hover:text-red-800">Remove</button>
                        </div>
                        <div class="mt-3 grid gap-3">
                            <select data-qc-field="reason_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">No issue</option>
                                @foreach($negativeReasons as $reason)
                                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                @endforeach
                            </select>
                            <div class="grid grid-cols-2 gap-3">
                                <select data-qc-field="section_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Section (optional)</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                                    @endforeach
                                </select>
                                <input data-qc-field="negative_points" type="number" min="0" max="5" value="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Points 0-5">
                            </div>
                            <input data-qc-field="site_name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Site / account (optional)">
                            <textarea data-qc-field="comment" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Issue comment"></textarea>
                        </div>
                    </div>
                </template>

                <template id="qc-bonus-template">
                    <div data-qc-item="bonus" class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p data-qc-item-label class="text-xs font-semibold uppercase text-slate-500">Bonus</p>
                            <button type="button" data-remove-qc-item class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">Remove</button>
                        </div>
                        <div class="mt-3 grid gap-3">
                            <select data-qc-field="reason_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">No bonus</option>
                                @foreach($bonusReasons as $reason)
                                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                @endforeach
                            </select>
                            <input data-qc-field="points" type="number" min="1" max="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Points 1-5">
                            <textarea data-qc-field="comment" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Bonus comment"></textarea>
                        </div>
                    </div>
                </template>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const configureDynamicRows = ({
                            listId,
                            templateId,
                            addButtonId,
                            groupName,
                            itemLabel,
                            maxItems,
                        }) => {
                            const list = document.getElementById(listId);
                            const template = document.getElementById(templateId);
                            const addButton = document.getElementById(addButtonId);

                            if (!list || !template || !addButton) {
                                return;
                            }

                            const reindex = () => {
                                const rows = Array.from(list.querySelectorAll(':scope > [data-qc-item]'));

                                rows.forEach((row, index) => {
                                    const label = row.querySelector('[data-qc-item-label]');
                                    const removeButton = row.querySelector('[data-remove-qc-item]');

                                    if (label) {
                                        label.textContent = `${itemLabel} ${index + 1}`;
                                    }

                                    row.querySelectorAll('[data-qc-field]').forEach((field) => {
                                        field.name = `${groupName}[${index}][${field.dataset.qcField}]`;
                                    });

                                    if (removeButton) {
                                        removeButton.classList.toggle('hidden', index === 0);
                                    }
                                });

                                addButton.disabled = rows.length >= maxItems;
                            };

                            addButton.addEventListener('click', () => {
                                const rows = list.querySelectorAll(':scope > [data-qc-item]');

                                if (rows.length >= maxItems) {
                                    return;
                                }

                                list.appendChild(template.content.cloneNode(true));
                                reindex();

                                const newRow = list.lastElementChild;
                                newRow?.querySelector('select, input, textarea')?.focus();
                            });

                            list.addEventListener('click', (event) => {
                                const removeButton = event.target.closest('[data-remove-qc-item]');

                                if (!removeButton) {
                                    return;
                                }

                                const rows = list.querySelectorAll(':scope > [data-qc-item]');

                                if (rows.length <= 1) {
                                    return;
                                }

                                removeButton.closest('[data-qc-item]')?.remove();
                                reindex();
                            });

                            reindex();
                        };

                        configureDynamicRows({
                            listId: 'qc-issues-list',
                            templateId: 'qc-issue-template',
                            addButtonId: 'add-qc-issue',
                            groupName: 'issues',
                            itemLabel: 'Issue',
                            maxItems: 50,
                        });

                        configureDynamicRows({
                            listId: 'qc-bonus-list',
                            templateId: 'qc-bonus-template',
                            addButtonId: 'add-qc-bonus',
                            groupName: 'bonus_items',
                            itemLabel: 'Bonus',
                            maxItems: 5,
                        });
                    });
                </script>
            @endif
        </aside>
    </div>
</div>
@endsection
