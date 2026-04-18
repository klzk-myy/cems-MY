@extends('layouts.base')

@section('title', 'Setup MFA')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Setup MFA</h3></div>
    <div class="card-body">
        <p class="text-[--color-ink-muted] mb-6">Scan the QR code below with your authenticator app:</p>

        <div class="flex justify-center mb-6">
            <div class="p-4 bg-white rounded">
                {!! $qrCode ?? '<p class="text-[--color-ink-muted]">QR Code will appear here</p>' !!}
            </div>
        </div>

        <form method="POST" action="{{ route('mfa.setup') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">Enter Verification Code</label>
                <input type="text" name="code" class="form-input" placeholder="000000" maxlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary">Verify & Enable</button>
        </form>
    </div>
</div>
@endsection