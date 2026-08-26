@extends('errors.layout')

@section('code', '419')
@section('title', 'Session Expired')
@section('message', 'Your session has expired for security reasons. Please sign in again to continue.')

@section('actions')
    <x-button href="{{ route('login') }}" variant="primary">Return to Login</x-button>
@endsection
