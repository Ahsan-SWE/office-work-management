@extends('layouts.admin')

@section('content')
    <div class="mb-5">
        <h2 class="text-xl font-bold">Audit Logs</h2>
        <p class="mt-1 text-sm text-slate-500">Immutable administration and system change history.</p>
    </div>

    <form method="GET" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[220px_1fr_1fr_auto]">
        <select name="action" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">All actions</option>
            @foreach($actions as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
            @endforeach
        </select>

        <select name="user_id" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">All actors</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((string)request('user_id') === (string)$user->id)>
                    {{ $user->name }} — {{ $user->email }}
                </option>
            @endforeach
        </select>

        <input name="entity_type" value="{{ request('entity_type') }}" placeholder="Entity type..." class="rounded-lg border border-slate-300 px-3 py-2">

        <button class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Time</th>
                        <th class="px-5 py-3">Actor</th>
                        <th class="px-5 py-3">Action</th>
                        <th class="px-5 py-3">Entity</th>
                        <th class="px-5 py-3 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-5 py-4">{{ $log->actor?->name ?? 'System' }}</td>
                        <td class="px-5 py-4 font-semibold">{{ $log->action }}</td>
                        <td class="px-5 py-4">{{ class_basename($log->entity_type) }} @if($log->entity_id) #{{ $log->entity_id }} @endif</td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.audit-logs.show', $log) }}" class="font-semibold">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No audit logs found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
@endsection
