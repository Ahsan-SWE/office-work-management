@extends('layouts.role')

@section('page-title', 'Team Performance')
@section('page-subtitle', 'Monthly QC quality totals for employees in your current team.')

@section('content')
    <div class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Team</p>
                    <h2 class="text-lg font-bold">{{ $team->name }}</h2>
                </div>
                <form method="GET" action="{{ route('team-leader.performance.index') }}" class="flex items-end gap-3">
                    <div>
                        <label for="month" class="block text-sm font-semibold text-slate-700">Performance month</label>
                        <input
                            id="month"
                            name="month"
                            type="month"
                            value="{{ $selectedMonth->format('Y-m') }}"
                            class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
                        >
                    </div>
                    <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">
                        View
                    </button>
                </form>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Reviews</th>
                            <th class="px-4 py-3">Negative</th>
                            <th class="px-4 py-3">Bonus</th>
                            <th class="px-4 py-3">Quality Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($members as $member)
                            @php $performance = $performances->get($member->id); @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{{ $member->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $member->email }}</p>
                                </td>
                                @if ($performance)
                                    <td class="px-4 py-3">{{ $performance->review_count }}</td>
                                    <td class="px-4 py-3">{{ $performance->negative_points }}</td>
                                    <td class="px-4 py-3">{{ $performance->bonus_points }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $performance->rating->label() }}</td>
                                @else
                                    <td class="px-4 py-3 text-slate-400">—</td>
                                    <td class="px-4 py-3 text-slate-400">—</td>
                                    <td class="px-4 py-3 text-slate-400">—</td>
                                    <td class="px-4 py-3 font-semibold text-slate-500">No QC Data</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No employees in this team.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($members->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
@endsection
