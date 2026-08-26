@extends('errors.layout')

@section('code', '404')
@section('title', 'Page Not Found')
@section('message', 'The page you are looking for could not be found. It may have been moved or no longer exists.')

@section('actions')
    <x-button href="{{ route('dashboard') }}" variant="secondary">Return to Dashboard</x-button>
@endsection
