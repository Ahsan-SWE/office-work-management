@extends('layouts.role')

@section('page-title', $label)
@section('page-subtitle', 'Foundation shell')

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h2 class="text-xl font-bold">{{ $label }}</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">
            Navigation and authorization for this section are ready. The business workflow is activated in its implementation milestone.
        </p>
    </div>
@endsection
