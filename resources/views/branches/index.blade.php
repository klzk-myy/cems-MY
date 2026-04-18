@extends('layouts.base')

@section('title', 'Branches')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Branches</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage branch locations</p>
</div>
@endsection

@section('header-actions')
@if(auth()->user()->isAdmin())
<div class="flex items-center gap-3">
    <a href="/branches/create" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Branch
    </a>
</div>
@endif
@endsection

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($branches ?? [] as $branch)
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $branch->name }}</h3>
            @if($branch->is_active)
                <span class="badge badge-success">Active</span>
            @else
                <span class="badge badge-default">Inactive</span>
            @endif
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-[--color-ink-muted] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium">Address</p>
                        <p class="text-sm text-[--color-ink-muted]">{{ $branch->address ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-[--color-ink-muted] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium">{{ $branch->phone ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-[--color-ink-muted] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <div>
                        <p class="text-sm text-[--color-ink-muted]">{{ $branch->email ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer flex gap-2">
            <a href="/branches/{{ $branch->id }}" class="btn btn-ghost btn-sm flex-1">View</a>
            @if(auth()->user()->isAdmin())
                <a href="/branches/{{ $branch->id }}/edit" class="btn btn-secondary btn-sm flex-1">Edit</a>
            @endif
        </div>
    </div>
    @empty
    <div class="col-span-full">
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <p class="empty-state-title">No branches found</p>
                    <p class="empty-state-description">Create your first branch to get started</p>
                    @if(auth()->user()->isAdmin())
                        <a href="/branches/create" class="btn btn-primary mt-4">Add Branch</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
