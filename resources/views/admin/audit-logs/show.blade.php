@extends('layouts.admin')

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('admin.audit-logs.index') }}" class="text-sm font-semibold text-slate-500">← Audit Logs</a>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold">{{ $auditLog->action }}</h2>

            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Actor</dt><dd class="font-semibold">{{ $auditLog->actor?->name ?? 'System' }}</dd></div>
                <div><dt class="text-slate-500">Time</dt><dd class="font-semibold">{{ $auditLog->created_at?->format('Y-m-d H:i:s') }}</dd></div>
                <div><dt class="text-slate-500">Entity</dt><dd class="font-semibold">{{ $auditLog->entity_type }}</dd></div>
                <div><dt class="text-slate-500">Entity ID</dt><dd class="font-semibold">{{ $auditLog->entity_id ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">IP</dt><dd class="font-semibold">{{ $auditLog->ip_address ?? '—' }}</dd></div>
            </dl>

            <div class="mt-6 grid gap-5 lg:grid-cols-2">
                <div>
                    <h3 class="font-bold">Old values</h3>
                    <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
                <div>
                    <h3 class="font-bold">New values</h3>
                    <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    </div>
@endsection
