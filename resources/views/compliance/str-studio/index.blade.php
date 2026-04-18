@extends('layouts.base')

@section('title', 'STR Studio')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">STR Studio</h1>
    <p class="text-sm text-[--color-ink-muted]">Create and manage Suspicious Transaction Reports</p>
</div>
@endsection

@section('header-actions')
<a href="/str/create" class="btn btn-primary">New STR</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">STR Drafts</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drafts ?? [] as $draft)
                <tr>
                    <td class="font-mono">#{{ $draft->id }}</td>
                    <td>{{ $draft->reference_number ?? 'DRAFT' }}</td>
                    <td><span class="badge badge-warning">Draft</span></td>
                    <td>{{ $draft->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="/str/{{ $draft->id }}" class="btn btn-ghost btn-sm">Edit</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No drafts</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
