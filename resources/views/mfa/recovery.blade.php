@extends('layouts.base')

@section('title', 'MFA Recovery')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Recover Account</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('mfa.recovery') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">Enter Recovery Code</label>
                <input type="text" name="recovery_code" class="form-input" placeholder="XXXX-XXXX" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary">Recover Account</button>
        </form>
    </div>
</div>
@endsection