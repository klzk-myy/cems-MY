@extends('layouts.base')

@section('title', 'Recovery Codes')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">MFA Recovery Codes</h3></div>
    <div class="card-body">
        <div class="alert alert-warning mb-6">
            <p class="font-medium">Save these codes in a safe place.</p>
            <p class="text-sm mt-1">Each code can only be used once to access your account.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            @foreach($recoveryCodes ?? [] as $code)
            <div class="p-3 bg-[--color-surface-elevated] rounded font-mono text-center">
                {{ $code }}
            </div>
            @endforeach
        </div>

        <a href="{{ route('dashboard') }}" class="btn btn-primary">Done</a>
    </div>
</div>
@endsection