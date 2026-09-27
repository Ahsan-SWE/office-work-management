@extends('layouts.role')

@section('page-title', 'Improvement Session')
@section('page-subtitle', $session->employee->name.' · '.$session->performance_month->format('F Y'))

@section('content')
@include('team-leader.improvement-sessions.partials.manager', [
    'updateRoute' => route('team-leader.improvement-sessions.update', $session),
    'startRoute' => route('team-leader.improvement-sessions.start', $session),
    'completeRoute' => route('team-leader.improvement-sessions.complete', $session),
])
@endsection
