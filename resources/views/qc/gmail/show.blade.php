@extends('layouts.role')

@section('page-title', 'QC Gmail')
@section('page-subtitle', 'Separate Gmail authorization used only for Major Error escalation emails.')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold">Personal QC Gmail connection</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    This is separate from normal Office Work Management sign-in. It requests Gmail send access only when you choose to connect it.
                </p>
            </div>
            @if($connection?->isConnected())
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">CONNECTED</span>
            @else
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">DISCONNECTED</span>
            @endif
        </div>

        @if($connection?->isConnected())
            <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm">
                <p><span class="text-slate-500">Gmail:</span> <strong>{{ $connection->email }}</strong></p>
                <p class="mt-1"><span class="text-slate-500">Connected:</span> {{ $connection->connected_at?->format('Y-m-d H:i') }}</p>
                @if($connection->last_refreshed_at)
                    <p class="mt-1"><span class="text-slate-500">Token refreshed:</span> {{ $connection->last_refreshed_at->format('Y-m-d H:i') }}</p>
                @endif
                @if($connection->last_error_message)
                    <div class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-red-700">
                        Last Gmail error: {{ $connection->last_error_message }}
                    </div>
                @endif
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('qc.gmail.redirect') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold">
                    Reconnect / refresh consent
                </a>
                <form method="POST" action="{{ route('qc.gmail.disconnect') }}">
                    @csrf
                    <button class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white">
                        Disconnect Gmail
                    </button>
                </form>
            </div>
        @else
            <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                Connect the same Gmail address as your Office Work Management account. Normal Google login remains unchanged.
            </div>
            <a href="{{ route('qc.gmail.redirect') }}" class="mt-4 inline-block rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">
                Connect QC Gmail
            </a>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold">Recent Major Error email attempts</h2>
        <p class="mt-1 text-sm text-slate-500">Delivery attempts remain as audit history after disconnect/reconnect.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Review</th>
                        <th class="px-3 py-2">Attempt</th>
                        <th class="px-3 py-2">Recipient</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attempts as $attempt)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-3 font-semibold">{{ $attempt->review?->review_code }}</td>
                            <td class="px-3 py-3">#{{ $attempt->attempt_no }}</td>
                            <td class="px-3 py-3">{{ $attempt->recipient_email }}</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $attempt->status === 'SENT' ? 'bg-emerald-100 text-emerald-800' : ($attempt->status === 'FAILED' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $attempt->status }}
                                </span>
                                @if($attempt->error_message)
                                    <p class="mt-1 max-w-lg text-xs text-red-700">{{ $attempt->error_message }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3">{{ $attempt->attempted_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No Major Error Gmail attempts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
