@extends('layouts.base')

@section('title', 'Branch Details')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">{{ $branch->name ?? 'N/A' }}</h3>
        <div class="flex gap-2">
            <a href="{{ route('branches.edit', $branch->id ?? 0) }}" class="btn btn-secondary">Edit</a>
            <a href="{{ route('branches.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Branch Information</h4>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Type</dt>
                        <dd>
                            @if(isset($branch->type))
                                @statuslabel($branch->type)
                            @else
                                <span class="text-[--color-ink-muted]">N/A</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Parent Branch</dt>
                        <dd>{{ $branch->parent_name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Contact</dt>
                        <dd class="font-mono">{{ $branch->contact_number ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-[--color-ink-muted]">Address</dt>
                        <dd>{{ $branch->address ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </div>
            <div>
                <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Statistics</h4>
                <dl class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-[--color-surface-elevated] rounded">
                        <dt class="text-sm text-[--color-ink-muted]">Counters</dt>
                        <dd class="text-2xl font-mono">{{ $stats['counters'] ?? 0 }}</dd>
                    </div>
                    <div class="p-4 bg-[--color-surface-elevated] rounded">
                        <dt class="text-sm text-[--color-ink-muted]">Staff</dt>
                        <dd class="text-2xl font-mono">{{ $stats['staff'] ?? 0 }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if(!empty($childBranches))
        <div class="mt-8">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Child Branches</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Contact</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($childBranches as $child)
                    <tr>
                        <td><a href="{{ route('branches.show', $child->id) }}" class="text-primary hover:underline">{{ $child->name }}</a></td>
                        <td>
                            @if(isset($child->type))
                                @statuslabel($child->type)
                            @endif
                        </td>
                        <td class="font-mono">{{ $child->contact_number ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection