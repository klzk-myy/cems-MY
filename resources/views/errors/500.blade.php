@extends('errors.layout')

@section('code', '500')
@section('title', 'Server Error')
@section('message', 'Something went wrong on our end. The issue has been logged and our team has been notified. Please try again later.')

@section('actions')
    <x-button href="{{ route('dashboard') }}" variant="secondary">Return to Dashboard</x-button>
@endsection
