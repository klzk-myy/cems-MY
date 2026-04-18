@extends('layouts.base')

@section('title', 'AML Rules')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">AML Rules</h1>
    <p class="text-sm text-[--color-ink-muted]">Configure AML rule engine</p>
</div>
@endsection

@section('header-actions')
<a href="/compliance/rules/create" class="btn btn-primary">Add Rule</a>
@endsection

@section('content')
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Hits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules ?? [] as $rule)
                <tr>
                    <td class="font-medium">{{ $rule->name }}</td>
                    <td><span class="badge badge-default">{{ $rule->type->label() ?? 'N/A' }}</span></td>
                    <td>
                        @if($rule->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </td>
                    <td class="font-mono">{{ number_format($rule->hit_count ?? 0) }}</td>
                    <td>
                        <a href="/compliance/rules/{{ $rule->id }}" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-12 text-[--color-ink-muted]">No rules configured</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
