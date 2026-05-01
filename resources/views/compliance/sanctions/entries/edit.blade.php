@extends('layouts.base')

@section('title', 'Edit Sanction Entry')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Edit Sanction Entry</h1>
    <p class="text-sm text-[--color-ink-muted]">Update sanctioned entity information</p>
</div>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="p-6">
            <form method="POST" action="{{ route('compliance.sanctions.entries.update', $entry['id']) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Sanction List *</label>
                    <select name="list_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="">Select a list</option>
                        @foreach($lists as $list)
                            <option value="{{ $list['id'] }}" {{ old('list_id', $entry['list_id']) == $list['id'] ? 'selected' : '' }}>
                                {{ $list['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('list_id')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Entity Name *</label>
                    <input type="text" name="entity_name" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ old('entity_name', $entry['entity_name']) }}" required>
                    @error('entity_name')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Entity Type *</label>
                    <select name="entity_type" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="">Select type</option>
                        <option value="individual" {{ old('entity_type', $entry['entity_type']) == 'individual' ? 'selected' : '' }}>Individual</option>
                        <option value="entity" {{ old('entity_type', $entry['entity_type']) == 'entity' ? 'selected' : '' }}>Entity</option>
                    </select>
                    @error('entity_type')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Aliases</label>
                    <input type="text" name="aliases" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ old('aliases', is_array($entry['aliases'] ?? null) ? implode(', ', $entry['aliases']) : ($entry['aliases'] ?? '')) }}" placeholder="Comma-separated names">
                    <p class="text-sm text-[--color-ink-muted] mt-1">Alternative names, separated by commas</p>
                    @error('aliases')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-[--color-ink] mb-1">Nationality</label>
                        <input type="text" name="nationality" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ old('nationality', $entry['nationality']) }}">
                        @error('nationality')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-[--color-ink] mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ old('date_of_birth', $entry['date_of_birth'] ?? null) }}">
                        @error('date_of_birth')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-[--color-ink] mb-1">Reference Number</label>
                        <input type="text" name="reference_number" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ old('reference_number', $entry['reference_number']) }}">
                        @error('reference_number')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-[--color-ink] mb-1">Listing Date</label>
                        <input type="date" name="listing_date" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ old('listing_date', $entry['listing_date'] ?? null) }}">
                        @error('listing_date')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Additional Details</label>
                    <textarea name="details" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="3">{{ old('details', is_array($entry['details'] ?? null) ? json_encode($entry['details']) : ($entry['details'] ?? '')) }}</textarea>
                    @error('details')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <a href="{{ route('compliance.sanctions.entries.show', $entry['id']) }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Cancel</a>
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">Update Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
