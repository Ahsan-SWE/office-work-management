@extends('layouts.role')

@section('page-title', 'My Team')
@section('page-subtitle', $team->name)

@section('content')
    <form method="GET" class="mb-4 flex gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <input name="q" value="{{ request('q') }}" placeholder="Search employee..." class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
        <button class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Search</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Employee</th>
                        <th class="px-5 py-3">Capabilities</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($members as $member)
                    <tr>
                        <td class="px-5 py-4">
                            <span class="font-semibold">{{ $member->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $member->email }}</span>
                        </td>
                        <td class="px-5 py-4">
                            {{ $member->capabilities->pluck('capability')->map(fn($v) => $v->value)->join(', ') ?: '—' }}
                        </td>
                        <td class="px-5 py-4">{{ $member->status->value }}</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('team-leader.members.show', $member) }}" class="font-semibold">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No employees in this team yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $members->links() }}</div>
@endsection
