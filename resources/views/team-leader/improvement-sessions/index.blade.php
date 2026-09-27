@extends('layouts.role')

@section('page-title', 'Improvement Sessions')
@section('page-subtitle', 'Manage Poor-month quality follow-up for employees in your current team.')

@section('content')
<div class="space-y-4">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Current team</p>
        <p class="font-bold">{{ $team->name }}</p>
    </section>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Employee</th>
                    <th class="px-4 py-3">Month</th>
                    <th class="px-4 py-3">Trigger</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($sessions as $session)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $session->employee->name }}</p>
                            <p class="text-xs text-slate-500">{{ $session->employee->email }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $session->performance_month->format('F Y') }}</td>
                        <td class="px-4 py-3">{{ $session->trigger_negative_points }} negative</td>
                        <td class="px-4 py-3">{{ $session->status->label() }}</td>
                        <td class="px-4 py-3 text-right">
                            <a class="font-semibold text-slate-700 underline"
                               href="{{ route('team-leader.improvement-sessions.show', $session) }}">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                            No Improvement Sessions for your current team.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sessions->links() }}
</div>
@endsection
