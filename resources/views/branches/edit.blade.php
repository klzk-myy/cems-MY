@extends('layouts.base')

@section('title', 'Edit Branch')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Edit Branch</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('branches.update', $branch->id ?? 0) }}">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Branch Name</label>
                    <input type="text" name="name" class="form-input" value="{{ $branch->name ?? '' }}" required>
                </div>
                <div>
                    <label class="form-label">Branch Type</label>
                    <select name="type" class="form-input" required>
                        @foreach($branchTypes ?? [] as $type)
                        <option value="{{ $type }}" @if($branch->type === $type) selected @endif>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Parent Branch</label>
                    <select name="parent_id" class="form-input">
                        <option value="">None (HQ)</option>
                        @foreach($parentBranches ?? [] as $parent)
                        <option value="{{ $parent->id }}" @if($branch->parent_id === $parent->id) selected @endif>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-input" value="{{ $branch->contact_number ?? '' }}">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input" rows="2">{{ $branch->address ?? '' }}</textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Update Branch</button>
                <a href="{{ route('branches.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection