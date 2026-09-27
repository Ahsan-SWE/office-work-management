@extends('layouts.admin')

@section('page-title', 'Improvement Session')
@section('page-subtitle', $session->employee->name.' · '.$session->performance_month->format('F Y'))

@section('content')
@include('team-leader.improvement-sessions.partials.manager', [
    'updateRoute' => route('admin.improvement-sessions.update', $session),
    'startRoute' => route('admin.improvement-sessions.start', $session),
    'completeRoute' => route('admin.improvement-sessions.complete', $session),
])
@endsection
