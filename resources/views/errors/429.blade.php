@extends('errors.layout')

@section('code', '429')
@section('title', 'Too Many Requests')
@section('message', 'You have made too many requests in a short period. Please wait a moment before trying again, or sign in again to continue.')

@section('actions')
    <x-button href="{{ route('login') }}" variant="primary">Return to Login</x-button>
@endsection
