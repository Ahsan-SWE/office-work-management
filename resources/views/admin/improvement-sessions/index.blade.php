@extends('layouts.admin')

@section('page-title', 'Improvement Sessions')
@section('page-subtitle', 'All Poor-month quality Improvement Sessions and follow-up status.')

@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Employee</th>
                <th class="px-4 py-3">Trigger Team</th>
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
                    <td class="px-4 py-3">{{ $session->triggerTeam?->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $session->performance_month->format('F Y') }}</td>
                    <td class="px-4 py-3">{{ $session->trigger_negative_points }} negative</td>
                    <td class="px-4 py-3">{{ $session->status->label() }}</td>
                    <td class="px-4 py-3 text-right">
                        <a class="font-semibold underline"
                           href="{{ route('admin.improvement-sessions.show', $session) }}">
                            Open
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                        No Improvement Sessions yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $sessions->links() }}</div>
@endsection
