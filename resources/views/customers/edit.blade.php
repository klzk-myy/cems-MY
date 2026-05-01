@extends('layouts.base')

@section('title', 'Edit Customer')

@section('content')
<div class="card max-w-3xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Edit Customer</h3></div>
    <div class="p-6">
        <form method="POST" action="{{ route('customers.update', $customer->id ?? 0) }}">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Full Name</label>
                    <input type="text" name="full_name" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ $customer->full_name ?? '' }}" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">ID Type</label>
                    <select name="id_type" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        @foreach($idTypes ?? [] as $value => $label)
                            <option value="{{ $value }}" {{ ($customer->id_type ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">ID Number</label>
                    <input type="text" name="id_number" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ $decryptedIdNumber ?? '' }}" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ $customer->email ?? '' }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Phone</label>
                    <input type="text" name="phone" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ $customer->phone ? decrypt($customer->phone) : '' }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Nationality</label>
                    <select name="nationality" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        @foreach($nationalities ?? [] as $nation)
                            <option value="{{ $nation }}" {{ ($customer->nationality ?? '') === $nation ? 'selected' : '' }}>{{ $nation }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Address</label>
                    <textarea name="address" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2">{{ $customer->address ? decrypt($customer->address) : '' }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Risk Rating</label>
                    <select name="risk_rating" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        @foreach($riskRatings ?? ['Low', 'Medium', 'High'] as $rating)
                            <option value="{{ $rating }}" {{ ($customer->risk_rating ?? '') === $rating ? 'selected' : '' }}>{{ $rating }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">Update Customer</button>
                <a href="{{ route('customers.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection