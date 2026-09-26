@extends('layouts.admin')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-5">
            <a href="{{ route('admin.teams.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">← Teams</a>
            <h2 class="mt-2 text-xl font-bold">{{ $team->exists ? 'Edit Team' : 'Create Team' }}</h2>
        </div>

        <form
            method="POST"
            action="{{ $team->exists ? route('admin.teams.update', $team) : route('admin.teams.store') }}"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
            @csrf
            @if($team->exists) @method('PUT') @endif

            <label class="block">
                <span class="text-sm font-semibold">Team name</span>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $team->name) }}"
                    required
                    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-slate-500 focus:outline-none"
                >
            </label>

            <label class="mt-5 block">
                <span class="text-sm font-semibold">Primary Team Leader</span>
                <select name="team_leader_id" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                    <option value="">Not assigned yet</option>
                    @foreach($leaders as $leader)
                        <option value="{{ $leader->id }}" @selected((string) old('team_leader_id', $team->team_leader_id) === (string) $leader->id)>
                            {{ $leader->name }} — {{ $leader->email }} ({{ $leader->status->value }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-slate-500">One Team Leader can lead only one active team in the MVP.</p>
            </label>

            <div class="mt-6 flex flex-wrap gap-3">
                <button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                    {{ $team->exists ? 'Save Changes' : 'Create Team' }}
                </button>
            </div>
        </form>

        @if($team->exists)
            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-bold">Team status</h3>
                <p class="mt-1 text-sm text-slate-500">Deactivation preserves history. A deactivated team must be reactivated before normal use.</p>

                <div class="mt-4">
                    @if($team->status->value === 'ACTIVE')
                        <form method="POST" action="{{ route('admin.teams.deactivate', $team) }}">
                            @csrf
                            <button class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">
                                Deactivate Team
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.teams.reactivate', $team) }}">
                            @csrf
                            <button class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                                Reactivate Team
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
