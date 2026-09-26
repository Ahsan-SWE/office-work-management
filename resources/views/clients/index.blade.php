@extends(auth()->user()->hasRole('SUPER_ADMIN') ? 'layouts.admin' : 'layouts.role')

@section('page-title', 'Clients')
@section('page-subtitle', 'Long-running client directory and work history container')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">Clients</h2>
            <p class="mt-1 text-sm text-slate-500">Create each client once, then reuse it for repeated work.</p>
        </div>

        @can('clients.create')
            <a href="{{ route('clients.create') }}" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">
                + Add Client
            </a>
        @endcan
    </div>

    <form method="GET" class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_180px_220px_auto]">
        <input name="q" value="{{ request('q') }}" placeholder="Search client name or CL-000001..." class="rounded-lg border border-slate-300 px-3 py-2">

        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                    {{ $status->value }}
                </option>
            @endforeach
        </select>

        <select name="tier_id" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">All tiers</option>
            @foreach($tiers as $tier)
                <option value="{{ $tier->id }}" @selected((string) request('tier_id') === (string) $tier->id)>
                    {{ $tier->name }}
                </option>
            @endforeach
        </select>

        <button class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Client</th>
                        <th class="px-5 py-3">Tier</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Sheet</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($clients as $client)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('clients.show', $client) }}" class="font-semibold hover:underline">
                                    {{ $client->name }}
                                </a>
                                <span class="block text-xs text-slate-500">{{ $client->client_code }}</span>
                            </td>
                            <td class="px-5 py-4">{{ $client->currentTier?->name ?? '—' }}</td>
                            <td class="px-5 py-4">{{ $client->status->value }}</td>
                            <td class="px-5 py-4">
                                <a href="{{ $client->google_sheet_url }}" target="_blank" rel="noopener" class="font-semibold text-blue-700">
                                    Open Sheet ↗
                                </a>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('clients.show', $client) }}" class="font-semibold">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $clients->links() }}</div>
@endsection
