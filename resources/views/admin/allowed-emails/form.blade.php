@extends('layouts.admin')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-5">
            <a href="{{ route('admin.allowed-emails.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">← Allowed Email</a>
            <h2 class="mt-2 text-xl font-bold">{{ $allowedEmail->exists ? 'Manage Approved Email' : 'Approve Gmail / Email' }}</h2>
        </div>

        <form
            method="POST"
            action="{{ $allowedEmail->exists ? route('admin.allowed-emails.update', $allowedEmail) : route('admin.allowed-emails.store') }}"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
            @csrf
            @if($allowedEmail->exists) @method('PUT') @endif

            @if($allowedEmail->exists && $allowedEmail->status->value === 'REGISTERED')
                <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
                    This email is already registered. Role/team/scopes must now be managed from the user account.
                </div>
            @endif

            <fieldset @disabled($allowedEmail->exists && $allowedEmail->status->value === 'REGISTERED')>
                <label class="block">
                    <span class="text-sm font-semibold">Google account email</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $allowedEmail->email) }}"
                        required
                        class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 disabled:bg-slate-100"
                    >
                </label>

                <label class="mt-5 block">
                    <span class="text-sm font-semibold">Preselected role</span>
                    <select id="role-select" name="preselected_role" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                        <option value="">Select role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('preselected_role', $allowedEmail->preselected_role?->value) === $role->value)>
                                {{ $role->value }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div id="employee-team" class="mt-5">
                    <label class="block">
                        <span class="text-sm font-semibold">Employee team</span>
                        <select name="preselected_team_id" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            <option value="">Select team</option>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}" @selected((string) old('preselected_team_id', $allowedEmail->preselected_team_id) === (string) $team->id)>
                                    {{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div id="qc-scope" class="mt-5">
                    <label class="block">
                        <span class="text-sm font-semibold">Initial QC scope</span>
                        <select name="preselected_qc_scope" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            <option value="">Select QC scope</option>
                            @foreach($qcScopes as $scope)
                                <option value="{{ $scope->value }}" @selected(old('preselected_qc_scope', $allowedEmail->preselected_qc_scope?->value) === $scope->value)>
                                    {{ $scope->value }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <button class="mt-6 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                    {{ $allowedEmail->exists ? 'Save Changes' : 'Approve Email' }}
                </button>
            </fieldset>
        </form>

        @if($allowedEmail->exists && $allowedEmail->status->value !== 'REGISTERED')
            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-bold">Invitation status</h3>
                <div class="mt-4">
                    @if($allowedEmail->status->value === 'PENDING')
                        <form method="POST" action="{{ route('admin.allowed-emails.disable', $allowedEmail) }}">
                            @csrf
                            <button class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">Disable Invitation</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.allowed-emails.enable', $allowedEmail) }}">
                            @csrf
                            <button class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">Enable Invitation</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <script>
        (() => {
            const role = document.getElementById('role-select');
            const employee = document.getElementById('employee-team');
            const qc = document.getElementById('qc-scope');

            function refresh() {
                const value = role?.value;
                employee.style.display = value === 'EMPLOYEE' ? 'block' : 'none';
                qc.style.display = value === 'QC' ? 'block' : 'none';
            }

            role?.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endsection
