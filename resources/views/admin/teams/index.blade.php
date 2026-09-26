@extends('layouts.admin')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">Teams</h2>
            <p class="mt-1 text-sm text-slate-500">Create teams and assign one primary Team Leader per active team.</p>
        </div>
        <a href="{{ route('admin.teams.create') }}" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
            + Create Team
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Team</th>
                        <th class="px-5 py-3">Team Leader</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($teams as $team)
                    <tr>
                        <td class="px-5 py-4 font-semibold">{{ $team->name }}</td>
                        <td class="px-5 py-4">
                            @if($team->teamLeader)
                                {{ $team->teamLeader->name }}
                                <span class="block text-xs text-slate-500">{{ $team->teamLeader->email }}</span>
                            @else
                                <span class="text-slate-400">Not assigned</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $team->status->value === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $team->status->value }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.teams.edit', $team) }}" class="font-semibold text-slate-700 hover:text-slate-950">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No teams created yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $teams->links() }}</div>
@endsection
