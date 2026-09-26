@extends('layouts.admin')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Active Teams', $stats['active_teams']],
            ['Active Users', $stats['active_users']],
            ['Pending Gmail Approvals', $stats['pending_invites']],
            ['Active QC Users', $stats['qc_users']],
        ] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-bold">Recent administration activity</h2>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($recentAudit as $item)
                    <div class="flex items-start justify-between gap-4 px-5 py-4">
                        <div>
                            <p class="text-sm font-semibold">{{ str_replace('_', ' ', $item->action) }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $item->actor?->name ?? 'System' }}
                                · {{ class_basename($item->entity_type) }}
                                @if($item->entity_id) #{{ $item->entity_id }} @endif
                            </p>
                        </div>
                        <p class="whitespace-nowrap text-xs text-slate-400">
                            {{ $item->created_at?->timezone(config('app.timezone'))->format('M j, H:i') }}
                        </p>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No administration activity yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold">Quick actions</h2>
            <div class="mt-4 grid gap-3">
                <a href="{{ route('admin.teams.create') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold hover:bg-slate-50">
                    + Create Team
                </a>
                <a href="{{ route('admin.allowed-emails.create') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold hover:bg-slate-50">
                    + Approve Gmail / Email
                </a>
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold hover:bg-slate-50">
                    Manage Users
                </a>
            </div>
        </section>
    </div>
@endsection
