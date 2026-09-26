@extends('layouts.admin')

@section('content')
    <div class="mb-5">
        <h2 class="text-xl font-bold">Users</h2>
        <p class="mt-1 text-sm text-slate-500">Manage account lifecycle, capabilities, QC scopes and permission overrides.</p>
    </div>

    <form method="GET" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_200px_200px_auto]">
        <input name="q" value="{{ request('q') }}" placeholder="Search name or email..." class="rounded-lg border border-slate-300 px-3 py-2">
        <select name="role" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">All roles</option>
            @foreach($roles as $role)
                <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->value }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>
            @endforeach
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">User</th>
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Team</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Action</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($users as $item)
                    <tr>
                        <td class="px-5 py-4">
                            <span class="font-semibold">{{ $item->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $item->email }}</span>
                        </td>
                        <td class="px-5 py-4">{{ $item->getRoleNames()->join(', ') }}</td>
                        <td class="px-5 py-4">{{ $item->primaryTeam?->name ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $item->status->value }}</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.users.show', $item) }}" class="font-semibold">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $users->links() }}</div>
@endsection
