@extends('layouts.base')

@section('title', 'Create Branch')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Create New Branch</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('branches.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Branch Name</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Branch Type</label>
                    <select name="type" class="form-input" required>
                        @foreach($branchTypes ?? [] as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Parent Branch</label>
                    <select name="parent_id" class="form-input">
                        <option value="">None (HQ)</option>
                        @foreach($parentBranches ?? [] as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-input">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Create Branch</button>
                <a href="{{ route('branches.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection