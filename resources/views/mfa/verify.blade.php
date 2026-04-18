@extends('layouts.base')

@section('title', 'Verify MFA')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Verify MFA</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('mfa.verify') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">Enter Verification Code</label>
                <input type="text" name="code" class="form-input" placeholder="000000" maxlength="6" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('mfa.recovery') }}" class="text-sm text-primary hover:underline">Use recovery code</a>
        </div>
    </div>
</div>
@endsection