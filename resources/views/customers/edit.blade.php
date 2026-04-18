@extends('layouts.base')

@section('title', 'Edit Customer')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Edit Customer</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.update', $customer->id ?? 0) }}">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" value="{{ $customer->full_name ?? '' }}" required>
                </div>
                <div>
                    <label class="form-label">ID Type</label>
                    <select name="id_type" class="form-input" required>
                        @foreach($idTypes ?? [] as $value => $label)
                            <option value="{{ $value }}" {{ ($customer->id_type ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">ID Number</label>
                    <input type="text" name="id_number" class="form-input" value="{{ $decryptedIdNumber ?? '' }}" required>
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="{{ $customer->email ?? '' }}">
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-input" value="{{ $customer->phone ? decrypt($customer->phone) : '' }}">
                </div>
                <div>
                    <label class="form-label">Nationality</label>
                    <select name="nationality" class="form-input" required>
                        @foreach($nationalities ?? [] as $nation)
                            <option value="{{ $nation }}" {{ ($customer->nationality ?? '') === $nation ? 'selected' : '' }}>{{ $nation }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input" rows="2">{{ $customer->address ? decrypt($customer->address) : '' }}</textarea>
                </div>
                <div>
                    <label class="form-label">Risk Rating</label>
                    <select name="risk_rating" class="form-input" required>
                        @foreach($riskRatings ?? ['Low', 'Medium', 'High'] as $rating)
                            <option value="{{ $rating }}" {{ ($customer->risk_rating ?? '') === $rating ? 'selected' : '' }}>{{ $rating }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Update Customer</button>
                <a href="{{ route('customers.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
