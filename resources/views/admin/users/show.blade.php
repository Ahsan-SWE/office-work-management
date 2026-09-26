@extends('layouts.admin')

@section('content')
    <div class="mx-auto max-w-5xl">
        <div class="mb-5">
            <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">← Users</a>
            <h2 class="mt-2 text-xl font-bold">{{ $managedUser->name }}</h2>
            <p class="text-sm text-slate-500">{{ $managedUser->email }}</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-bold">Account</h3>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Role</dt><dd class="font-semibold">{{ $managedUser->getRoleNames()->join(', ') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Primary team</dt><dd class="font-semibold">{{ $managedUser->primaryTeam?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Status</dt><dd class="font-semibold">{{ $managedUser->status->value }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Last login</dt><dd class="font-semibold">{{ $managedUser->last_login_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                </dl>

                <form method="POST" action="{{ route('admin.users.status', $managedUser) }}" class="mt-5">
                    @csrf
                    @method('PUT')
                    <label class="block text-sm font-semibold">Change status</label>
                    <div class="mt-2 flex gap-2">
                        <select name="status" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected($managedUser->status->value === $status->value)>{{ $status->value }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Update</button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Changing status revokes existing sessions.</p>
                </form>
            </section>

            @if($managedUser->hasRole('EMPLOYEE'))
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="font-bold">Employee capabilities</h3>
                    <form method="POST" action="{{ route('admin.users.capabilities', $managedUser) }}" class="mt-4">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-3">
                            @php $selectedCaps = $managedUser->capabilities->pluck('capability')->map(fn($v) => $v->value)->all(); @endphp
                            @foreach($capabilities as $capability)
                                <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3">
                                    <input type="checkbox" name="capabilities[]" value="{{ $capability->value }}" @checked(in_array($capability->value, $selectedCaps, true))>
                                    <span class="font-semibold">{{ $capability->value }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save Capabilities</button>
                    </form>
                </section>
            @endif

            @if($managedUser->hasRole('QC'))
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="font-bold">QC scopes</h3>
                    <form method="POST" action="{{ route('admin.users.qc-scopes', $managedUser) }}" class="mt-4">
                        @csrf
                        @method('PUT')
                        @php $selectedScopes = $managedUser->qcScopes->pluck('scope')->map(fn($v) => $v->value)->all(); @endphp
                        <div class="grid gap-3">
                            @foreach($qcScopes as $scope)
                                <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3">
                                    <input type="checkbox" name="qc_scopes[]" value="{{ $scope->value }}" @checked(in_array($scope->value, $selectedScopes, true))>
                                    <span class="font-semibold">{{ $scope->value }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save QC Scopes</button>
                    </form>
                </section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                <h3 class="font-bold">Permission overrides</h3>
                <p class="mt-1 text-sm text-slate-500">Explicit DENY or ALLOW takes precedence over the base role permission.</p>

                <form method="POST" action="{{ route('admin.users.permission-overrides.store', $managedUser) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_160px_auto]">
                    @csrf
                    <select name="permission_key" class="rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($permissions as $permission)
                            <option value="{{ $permission->name }}">{{ $permission->name }}</option>
                        @endforeach
                    </select>
                    <select name="decision" class="rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($decisions as $decision)
                            <option value="{{ $decision->value }}">{{ $decision->value }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save Override</button>
                </form>

                <div class="mt-5 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    @forelse($managedUser->permissionOverrides as $override)
                        <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                            <div>
                                <span class="font-semibold">{{ $override->permission_key }}</span>
                                <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-semibold {{ $override->decision->value === 'DENY' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $override->decision->value }}
                                </span>
                            </div>
                            <form method="POST" action="{{ route('admin.users.permission-overrides.destroy', [$managedUser, $override]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="font-semibold text-red-600">Remove</button>
                            </form>
                        </div>
                    @empty
                        <p class="px-4 py-4 text-sm text-slate-500">No explicit overrides.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
