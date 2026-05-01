@extends('layouts.base')

@section('title', 'Edit Branch - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Edit Branch</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">{{ $branch->code }} - {{ $branch->name }}</p>
    </div>
    <a href="{{ route('branches.show', $branch) }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        Back
    </a>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Branch Details</h3>
    </div>
    <div class="p-6">
        <form action="{{ route('branches.update', $branch) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Branch Code</label>
                    <input type="text" name="code" value="{{ $branch->code }}" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Branch Name</label>
                    <input type="text" name="name" value="{{ $branch->name }}" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Branch Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm">
                        @foreach($branchTypes as $type)
                        <option value="{{ $type }}" {{ $branch->type === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Parent Branch</label>
                    <select name="parent_id" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm">
                        <option value="">None (HQ)</option>
                        @foreach($parentBranches as $pb)
                        <option value="{{ $pb->id }}" {{ $branch->parent_id === $pb->id ? 'selected' : '' }}>{{ $pb->code }} - {{ $pb->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Address</label>
                    <textarea name="address" rows="2" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm">{{ $branch->address }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Phone</label>
                    <input type="text" name="phone" value="{{ $branch->phone }}" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Email</label>
                    <input type="email" name="email" value="{{ $branch->email }}" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm">
                </div>
            </div>
            <div class="mt-6 flex items-center gap-4">
                <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                    Update Branch
                </button>
                @if($branch->is_active)
                <form action="{{ route('branches.destroy', $branch) }}" method="POST" onsubmit="return confirm('Deactivate this branch?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50">
                        Deactivate
                    </button>
                </form>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection