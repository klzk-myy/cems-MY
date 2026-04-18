@extends('layouts.base')

@section('title', 'EDD Templates')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">EDD Templates</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage questionnaire templates</p>
</div>
@endsection

@section('content')
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Questions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates ?? [] as $template)
                <tr>
                    <td class="font-medium">{{ $template->name }}</td>
                    <td><span class="badge badge-default">{{ $template->type->label() ?? 'N/A' }}</span></td>
                    <td>{{ $template->questions->count() ?? 0 }}</td>
                    <td>
                        <a href="/compliance/edd-templates/{{ $template->id }}" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-12 text-[--color-ink-muted]">No templates found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
