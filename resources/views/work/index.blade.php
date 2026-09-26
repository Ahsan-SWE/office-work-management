@extends(auth()->user()->hasRole('SUPER_ADMIN') ? 'layouts.admin' : 'layouts.role')

@section('page-title', auth()->user()->hasRole('SUPER_ADMIN') ? 'All Work' : 'Team Work')
@section('page-subtitle', 'Work Orders and employee assignments')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-xl font-bold">Work Orders</h2>
        <p class="mt-1 text-sm text-slate-500">One Work Order can contain multiple independent employee assignments.</p>
    </div>
    <a href="{{ route('work.create') }}" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">+ Assign New Work</a>
</div>

<form method="GET" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_180px_180px_auto]">
    <input name="q" value="{{ request('q') }}" placeholder="Search work ID or client..." class="rounded-lg border border-slate-300 px-3 py-2">
    <select name="work_type" class="rounded-lg border border-slate-300 px-3 py-2">
        <option value="">All work types</option>
        @foreach($workTypes as $type)
            <option value="{{ $type->value }}" @selected(request('work_type')===$type->value)>{{ $type->label() }}</option>
        @endforeach
    </select>
    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2">
        <option value="">All statuses</option>
        @foreach(['PENDING','IN_PROGRESS','QC_IN_PROGRESS','REWORK','COMPLETED','CANCELLED','DUPLICATE'] as $status)
            <option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>
        @endforeach
    </select>
    <button class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Filter</button>
</form>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Work</th><th class="px-5 py-3">Client</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Priority</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Assignments</th><th class="px-5 py-3 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($workOrders as $workOrder)
                <tr>
                    <td class="px-5 py-4 font-semibold">{{ $workOrder->work_code }}</td>
                    <td class="px-5 py-4">{{ $workOrder->client->name }}<span class="block text-xs text-slate-500">{{ $workOrder->client->client_code }}</span></td>
                    <td class="px-5 py-4">{{ $workOrder->work_type->label() }}</td>
                    <td class="px-5 py-4">{{ $workOrder->priority->value }}</td>
                    <td class="px-5 py-4">{{ $workOrder->status->value }}</td>
                    <td class="px-5 py-4">{{ $workOrder->assignments_count }}</td>
                    <td class="px-5 py-4 text-right"><a href="{{ route('work.show',$workOrder) }}" class="font-semibold">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">No work orders yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-5">{{ $workOrders->links() }}</div>
@endsection
