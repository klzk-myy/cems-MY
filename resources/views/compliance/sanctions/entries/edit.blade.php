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
    <div class="card">
        <div class="card-body">
            <form method="POST" action="/compliance/sanctions/entries/{{ $entry['id'] }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label">Sanction List *</label>
                    <select name="list_id" class="form-select" required>
                        <option value="">Select a list</option>
                        @foreach($lists as $list)
                            <option value="{{ $list['id'] }}" {{ old('list_id', $entry['list_id']) == $list['id'] ? 'selected' : '' }}>
                                {{ $list['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('list_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Entity Name *</label>
                    <input type="text" name="entity_name" class="form-input" value="{{ old('entity_name', $entry['entity_name']) }}" required>
                    @error('entity_name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Entity Type *</label>
                    <select name="entity_type" class="form-select" required>
                        <option value="">Select type</option>
                        <option value="individual" {{ old('entity_type', $entry['entity_type']) == 'individual' ? 'selected' : '' }}>Individual</option>
                        <option value="entity" {{ old('entity_type', $entry['entity_type']) == 'entity' ? 'selected' : '' }}>Entity</option>
                    </select>
                    @error('entity_type')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Aliases</label>
                    <input type="text" name="aliases" class="form-input" value="{{ old('aliases', is_array($entry['aliases'] ?? null) ? implode(', ', $entry['aliases']) : ($entry['aliases'] ?? '')) }}" placeholder="Comma-separated names">
                    <p class="form-hint">Alternative names, separated by commas</p>
                    @error('aliases')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Nationality</label>
                        <input type="text" name="nationality" class="form-input" value="{{ old('nationality', $entry['nationality']) }}">
                        @error('nationality')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-input" value="{{ old('date_of_birth', $entry['date_of_birth'] ?? null) }}">
                        @error('date_of_birth')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-input" value="{{ old('reference_number', $entry['reference_number']) }}">
                        @error('reference_number')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Listing Date</label>
                        <input type="date" name="listing_date" class="form-input" value="{{ old('listing_date', $entry['listing_date'] ?? null) }}">
                        @error('listing_date')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Additional Details</label>
                    <textarea name="details" class="form-textarea" rows="3">{{ old('details', is_array($entry['details'] ?? null) ? json_encode($entry['details']) : ($entry['details'] ?? '')) }}</textarea>
                    @error('details')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <a href="/compliance/sanctions/entries/{{ $entry['id'] }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
