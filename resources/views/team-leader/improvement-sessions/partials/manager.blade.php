<div class="grid gap-5 xl:grid-cols-[1fr_420px]">
    <div class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">{{ $session->performance_month->format('F Y') }}</p>
                    <h2 class="text-xl font-bold">{{ $session->employee->name }}</h2>
                    <p class="text-sm text-slate-500">{{ $session->employee->email }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">
                    {{ $session->status->label() }}
                </span>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Trigger rating</p>
                    <p class="font-bold">{{ $session->trigger_rating->label() }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Negative</p>
                    <p class="font-bold">{{ $session->trigger_negative_points }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Bonus</p>
                    <p class="font-bold">{{ $session->trigger_bonus_points }}</p>
                </div>
            </div>
        </section>

        @if(in_array($session->status, [
            \App\Enums\ImprovementSessionStatus::OPEN,
            \App\Enums\ImprovementSessionStatus::IN_PROGRESS,
        ], true))
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">Plan and notes</h2>
                <form class="mt-4 space-y-4" method="POST" action="{{ $updateRoute }}">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="text-sm font-semibold">Improvement plan</label>
                        <textarea name="improvement_plan" rows="5"
                                  class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">{{ old('improvement_plan', $session->improvement_plan) }}</textarea>
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Internal Team Leader / Admin notes</label>
                        <p class="text-xs text-slate-500">Hidden from the employee.</p>
                        <textarea name="team_leader_notes" rows="4"
                                  class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">{{ old('team_leader_notes', $session->team_leader_notes) }}</textarea>
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Employee-visible notes</label>
                        <textarea name="employee_visible_notes" rows="4"
                                  class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">{{ old('employee_visible_notes', $session->employee_visible_notes) }}</textarea>
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Follow-up date</label>
                        <input type="date" name="follow_up_date"
                               value="{{ old('follow_up_date', $session->follow_up_date?->toDateString()) }}"
                               class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">
                        Save session details
                    </button>
                </form>

                <div class="mt-5 flex flex-wrap gap-3">
                    @if($session->status === \App\Enums\ImprovementSessionStatus::OPEN)
                        <form method="POST" action="{{ $startRoute }}">
                            @csrf
                            <button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">
                                Start Session
                            </button>
                        </form>
                    @endif

                    @if($session->status === \App\Enums\ImprovementSessionStatus::IN_PROGRESS)
                        <form method="POST" action="{{ $completeRoute }}">
                            @csrf
                            <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">
                                Complete Session
                            </button>
                        </form>
                    @endif
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                This session is read-only. Its history and notes remain available for audit.
            </section>
        @endif
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold">Full session history</h2>
        <div class="mt-4 space-y-4">
            @foreach($session->events as $event)
                <div class="border-l-2 border-slate-200 pl-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold">{{ $event->event_type->label() }}</p>
                        <span class="text-[11px] uppercase text-slate-400">{{ $event->visibility->value }}</span>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ $event->created_at->format('M j, Y g:i A') }}
                        @if($event->actor)
                            · {{ $event->actor->name }}
                        @endif
                    </p>
                    @if($event->note)
                        <p class="mt-1 text-sm text-slate-600">{{ $event->note }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6 border-t border-slate-200 pt-5 text-sm">
            <p><strong>Internal notes</strong></p>
            <p class="mt-1 whitespace-pre-line text-slate-600">{{ $session->team_leader_notes ?: 'None' }}</p>
            <p class="mt-4"><strong>Employee-visible notes</strong></p>
            <p class="mt-1 whitespace-pre-line text-slate-600">{{ $session->employee_visible_notes ?: 'None' }}</p>
        </div>
    </section>
</div>
