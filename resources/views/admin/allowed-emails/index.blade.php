@extends('layouts.admin')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">Allowed Gmail / Email</h2>
            <p class="mt-1 text-sm text-slate-500">Only pre-approved Google accounts may register.</p>
        </div>
        <a href="{{ route('admin.allowed-emails.create') }}" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
            + Approve Email
        </a>
    </div>

    <form method="GET" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_200px_auto]">
        <input name="q" value="{{ request('q') }}" placeholder="Search email..." class="rounded-lg border border-slate-300 px-3 py-2">
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
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Preselection</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($allowedEmails as $item)
                    <tr>
                        <td class="px-5 py-4">
                            <span class="font-semibold">{{ $item->email }}</span>
                            @if($item->registeredUser)
                                <span class="mt-1 block text-xs text-slate-500">Registered as {{ $item->registeredUser->name }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">{{ $item->preselected_role->value }}</td>
                        <td class="px-5 py-4 text-slate-600">
                            @if($item->preselected_role->value === 'EMPLOYEE')
                                Team: {{ $item->preselectedTeam?->name ?? 'Missing' }}
                            @elseif($item->preselected_role->value === 'QC')
                                QC: {{ $item->preselected_qc_scope?->value ?? 'Missing' }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-4">{{ $item->status->value }}</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.allowed-emails.edit', $item) }}" class="font-semibold">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No approved emails yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $allowedEmails->links() }}</div>
@endsection
