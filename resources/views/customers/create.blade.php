@extends('layouts.base')

@section('title', 'New Customer')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Create New Customer</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" value="{{ old('full_name') }}" required>
                </div>
                <div>
                    <label class="form-label">ID Type</label>
                    <select name="id_type" class="form-input" required>
                        @foreach($idTypes ?? [] as $value => $label)
                            <option value="{{ $value }}" {{ old('id_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">ID Number</label>
                    <input type="text" name="id_number" class="form-input" value="{{ old('id_number') }}" required>
                </div>
                <div>
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-input" value="{{ old('date_of_birth') }}" required>
                </div>
                <div>
                    <label class="form-label">Nationality</label>
                    <select name="nationality" class="form-input" required>
                        @foreach($nationalities ?? [] as $nation)
                            <option value="{{ $nation }}" {{ old('nationality') === $nation ? 'selected' : '' }}>{{ $nation }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="{{ old('email') }}">
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-input" value="{{ old('phone') }}">
                </div>
                <div>
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input" rows="2">{{ old('address') }}</textarea>
                </div>
                <div>
                    <label class="form-label">Risk Rating</label>
                    <p class="text-sm text-[--color-ink-muted]">Risk rating is automatically determined by the risk scoring system</p>
                    <span class="badge badge-info mt-1">Auto-determined</span>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Create Customer</button>
                <a href="{{ route('customers.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
