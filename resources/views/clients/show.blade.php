@extends(auth()->user()->hasRole('SUPER_ADMIN') ? 'layouts.admin' : 'layouts.role')

@section('page-title', $client->name)
@section('page-subtitle', $client->client_code)

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('clients.index') }}" class="text-sm font-semibold text-slate-500">← Clients</a>

        <div class="flex flex-wrap gap-2">
            @if($client->status->value === 'ACTIVE' && auth()->user()->can('work.assign'))
                <a href="{{ route('work.create', ['client' => $client->id]) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    + Assign Work
                </a>
            @endif

            @can('clients.update')
                <a href="{{ route('clients.edit', $client) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Edit Client</a>
            @endcan

            @can('clients.deactivate')
                @if($client->status->value === 'ACTIVE')
                    <form method="POST" action="{{ route('clients.deactivate', $client) }}">
                        @csrf
                        <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700">Deactivate</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('clients.reactivate', $client) }}">
                        @csrf
                        <button class="rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700">Reactivate</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[1fr_1fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold">Client details</h2>

            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Status</dt><dd class="mt-1 font-semibold">{{ $client->status->value }}</dd></div>
                <div><dt class="text-slate-500">Tier</dt><dd class="mt-1 font-semibold">{{ $client->currentTier?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Start Date</dt><dd class="mt-1 font-semibold">{{ optional($client->start_date)->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Expected End Date</dt><dd class="mt-1 font-semibold">{{ optional($client->expected_end_date)->format('Y-m-d') ?? '—' }}</dd></div>
            </dl>

            <div class="mt-5 rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active Google Sheet</p>
                <a href="{{ $client->google_sheet_url }}" target="_blank" rel="noopener" class="mt-2 block break-all font-semibold text-blue-700">
                    {{ $client->google_sheet_url }} ↗
                </a>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold">Recent work history</h2>
                    <p class="mt-1 text-sm text-slate-500">The same client is reused for repeated work.</p>
                </div>
                @if($workOrders->isNotEmpty())
                    <a href="{{ route('work.index', ['q' => $client->client_code]) }}" class="text-sm font-semibold">View All</a>
                @endif
            </div>

            <div class="mt-4 space-y-3">
                @forelse($workOrders as $workOrder)
                    <a href="{{ route('work.show', $workOrder) }}" class="block rounded-xl border border-slate-200 p-4 hover:border-slate-400">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ $workOrder->work_code }} · {{ $workOrder->work_type->label() }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $workOrder->assignments->count() }} assignment(s) · {{ $workOrder->created_at?->format('Y-m-d H:i') }}</p>
                            </div>
                            <span class="text-xs font-semibold">{{ $workOrder->status->value }}</span>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">No work orders yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold">Google Sheet URL history</h2>
            <div class="mt-4 space-y-3">
                @forelse($client->sheetUrlHistories as $history)
                    <div class="rounded-xl border border-slate-200 p-4 text-sm">
                        <a href="{{ $history->url }}" target="_blank" rel="noopener" class="break-all font-semibold text-blue-700">
                            {{ $history->url }}
                        </a>
                        <p class="mt-2 text-xs text-slate-500">
                            From {{ $history->effective_at?->format('Y-m-d H:i') }}
                            @if($history->ended_at)
                                to {{ $history->ended_at->format('Y-m-d H:i') }}
                            @else
                                · Current
                            @endif
                            @if($history->changer)
                                · by {{ $history->changer->name }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No URL history.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold">Tier history</h2>
            <div class="mt-4 space-y-3">
                @forelse($client->tierHistories as $history)
                    <div class="rounded-xl border border-slate-200 p-4 text-sm">
                        <p class="font-semibold">{{ $history->tier?->name ?? 'Unknown tier' }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            From {{ $history->effective_at?->format('Y-m-d H:i') }}
                            @if($history->ended_at)
                                to {{ $history->ended_at->format('Y-m-d H:i') }}
                            @else
                                · Current
                            @endif
                            @if($history->change_reason)
                                · {{ str_replace('_', ' ', $history->change_reason) }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No tier history yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
