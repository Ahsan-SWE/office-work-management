@extends('layouts.role')
@section('page-title','My Work')
@section('page-subtitle','Rework first, then Urgent, High, Normal, then oldest')
@section('content')
<div class="grid gap-4">
@forelse($assignments as $assignment)
    <a href="{{ route('employee.work.show',$assignment) }}" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-slate-400">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2"><p class="font-bold">{{ $assignment->assignment_code }}</p><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $assignment->status->value }}</span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $assignment->priority->value }}</span></div>
                <p class="mt-2 text-lg font-semibold">{{ $assignment->workOrder->client->name }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $assignment->workOrder->work_code }} · {{ $assignment->workOrder->work_type->label() }} @if($assignment->section)· {{ $assignment->section->name }}@endif</p>
            </div>
            <div class="text-right text-sm"><p class="text-slate-500">Progress</p><p class="font-bold">{{ $assignment->completed_count }}{{ $assignment->assigned_count!==null ? ' / '.$assignment->assigned_count:'' }}</p></div>
        </div>
        @if($assignment->scope_text)<p class="mt-4 text-sm text-slate-600">{{ $assignment->scope_text }}</p>@endif
    </a>
@empty
    <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">No active work assigned.</div>
@endforelse
</div>
@endsection
