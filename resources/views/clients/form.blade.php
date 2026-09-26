@extends(auth()->user()->hasRole('SUPER_ADMIN') ? 'layouts.admin' : 'layouts.role')

@section('page-title', $isEdit ? 'Edit Client' : 'Create Client')
@section('page-subtitle', 'Google Sheet remains the source of truth for site/account credentials')

@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ $isEdit ? route('clients.show', $client) : route('clients.index') }}" class="text-sm font-semibold text-slate-500">
            ← {{ $isEdit ? 'Client' : 'Clients' }}
        </a>

        @if(session('duplicate_warning'))
            <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950">
                <h2 class="font-bold">Possible duplicate client found</h2>
                <p class="mt-1 text-sm">Repeated weekly work should reuse the existing client. Create another client only when it is genuinely different.</p>

                <ul class="mt-3 space-y-1 text-sm">
                    @foreach(session('duplicate_warning') as $candidate)
                        <li>
                            <strong>{{ $candidate['name'] }}</strong>
                            ({{ $candidate['client_code'] ?? 'No code' }}, {{ $candidate['status'] }}, similarity {{ $candidate['score'] }}%)
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ $isEdit ? route('clients.update', $client) : route('clients.store') }}"
              class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="grid gap-5">
                <div>
                    <label class="text-sm font-semibold">Client Name *</label>
                    <input name="name" value="{{ old('name', $client->name) }}" required maxlength="220"
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5"
                           placeholder="Sam Andasi CC Doc">
                </div>

                <div>
                    <label class="text-sm font-semibold">Google Sheet URL *</label>
                    <input name="google_sheet_url"
                           value="{{ old('google_sheet_url', $client->google_sheet_url) }}"
                           required
                           class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5"
                           placeholder="https://docs.google.com/spreadsheets/d/...">
                    <p class="mt-1 text-xs text-slate-500">The application stores the link only. It does not read credentials from the Sheet in MVP.</p>
                </div>

                @if(!$isEdit || auth()->user()->hasRole('SUPER_ADMIN'))
                    <div>
                        <label class="text-sm font-semibold">
                            {{ $isEdit ? 'Current Tier (Super Admin correction only)' : 'Initial Tier' }}
                        </label>
                        <select name="current_tier_id" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            <option value="">No tier selected</option>
                            @foreach($tiers as $tier)
                                <option value="{{ $tier->id }}" @selected((string) old('current_tier_id', $client->current_tier_id) === (string) $tier->id)>
                                    {{ $tier->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($isEdit)
                            <p class="mt-1 text-xs text-amber-700">Normal tier upgrades will be applied by the Tier Upgrade job after QC approval. Use this only to correct data.</p>
                        @endif
                    </div>
                @elseif($isEdit)
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current Tier</p>
                        <p class="mt-1 font-bold">{{ $client->currentTier?->name ?? '—' }}</p>
                        <p class="mt-2 text-xs text-slate-500">Tier upgrades are handled through the approved Tier Upgrade workflow.</p>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold">Start Date</label>
                        <input type="date" name="start_date"
                               value="{{ old('start_date', optional($client->start_date)->format('Y-m-d')) }}"
                               class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Expected End Date</label>
                        <input type="date" name="expected_end_date"
                               value="{{ old('expected_end_date', optional($client->expected_end_date)->format('Y-m-d')) }}"
                               class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                        <p class="mt-1 text-xs text-slate-500">Informational only. Extending it never removes history.</p>
                    </div>
                </div>

                @if(session('duplicate_warning'))
                    <label class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
                        <input type="checkbox" name="confirm_duplicate" value="1" class="mt-1">
                        <span class="text-sm">
                            I checked the existing clients and confirm this should still be {{ $isEdit ? 'saved with this name' : 'created as a separate client' }}.
                        </span>
                    </label>
                @endif

                <div class="flex flex-wrap gap-3">
                    <button class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $isEdit ? 'Save Changes' : 'Create Client' }}
                    </button>
                    <a href="{{ $isEdit ? route('clients.show', $client) : route('clients.index') }}"
                       class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold">
                        Cancel
                    </a>
                </div>
            </div>
        </form>

        @can('tiers.create')
            <form method="POST" action="{{ route('tiers.store') }}" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="font-bold">Need a custom tier?</h2>
                <p class="mt-1 text-sm text-slate-500">New tiers are global. Existing used tiers are never hard-deleted.</p>
                <div class="mt-3 flex gap-2">
                    <input name="name" required maxlength="120" placeholder="VIP Tier"
                           class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
                    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Add Tier</button>
                </div>
            </form>
        @endcan
    </div>
@endsection
