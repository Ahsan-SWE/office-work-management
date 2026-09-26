@extends('layouts.role')

@section('page-title', 'Manage Employee')
@section('page-subtitle', $team->name)

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('team-leader.members.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">← My Team</a>

        <div class="mt-4 grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold">{{ $member->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $member->email }}</p>

                <form method="POST" action="{{ route('team-leader.members.status', $member) }}" class="mt-6">
                    @csrf
                    @method('PUT')
                    <label class="text-sm font-semibold">Employee status</label>
                    <div class="mt-2 flex gap-2">
                        <select name="status" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected($member->status->value === $status->value)>
                                    {{ $status->value }}
                                </option>
                            @endforeach
                        </select>
                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Update</button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Status changes revoke the employee’s existing sessions.</p>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">Capabilities</h2>
                @php
                    $selected = $member->capabilities->pluck('capability')->map(fn($v) => $v->value)->all();
                @endphp
                <form method="POST" action="{{ route('team-leader.members.capabilities', $member) }}" class="mt-4">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-3">
                        @foreach($capabilities as $capability)
                            <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3">
                                <input type="checkbox" name="capabilities[]" value="{{ $capability->value }}" @checked(in_array($capability->value, $selected, true))>
                                <span class="font-semibold">{{ $capability->value }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                        Save Capabilities
                    </button>
                </form>
            </section>
        </div>
    </div>
@endsection
