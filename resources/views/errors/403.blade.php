@extends('errors.layout')

@section('code', '403')
@section('title', 'Forbidden')
@section('message', 'You do not have permission to access this resource. If you believe this is a mistake, contact your system administrator.')

@section('actions')
    <x-button href="{{ route('dashboard') }}" variant="secondary">Return to Dashboard</x-button>
@endsection
