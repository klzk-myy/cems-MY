@extends('layouts.base')

@section('title', $entry['entity_name'] ?? 'Sanction Entry')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">{{ $entry['entity_name'] ?? 'Sanction Entry' }}</h1>
    <p class="text-sm text-[--color-ink-muted]">
        @if(($entry['status'] ?? '') === 'active')
            <span class="badge badge-success">Active</span>
        @else
            <span class="badge badge-default">Inactive</span>
        @endif
    </p>
</div>
@endsection

@section('header-actions')
<div class="flex gap-2">
    <a href="/compliance/sanctions/entries/{{ $entry['id'] }}/edit" class="btn btn-ghost">Edit</a>
    <a href="/compliance/sanctions/entries" class="btn btn-ghost">Back</a>
</div>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Entry Information</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Entity Name</p>
                    <p class="font-medium">{{ $entry['entity_name'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">List</p>
                    <p class="font-medium">{{ $entry['list_name'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Type</p>
                    <p class="font-medium">{{ ucfirst($entry['entity_type'] ?? 'N/A') }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Nationality</p>
                    <p class="font-medium">{{ $entry['nationality'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Date of Birth</p>
                    <p class="font-medium">{{ isset($entry['date_of_birth']) ? \Carbon\Carbon::parse($entry['date_of_birth'])->format('d M Y') : 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Reference Number</p>
                    <p class="font-medium font-mono">{{ $entry['reference_number'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Listing Date</p>
                    <p class="font-medium">{{ isset($entry['listing_date']) ? \Carbon\Carbon::parse($entry['listing_date'])->format('d M Y') : 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Status</p>
                    <p class="font-medium">
                        @if(($entry['status'] ?? '') === 'active')
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($entry['aliases']))
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Aliases</h3>
        </div>
        <div class="card-body">
            <div class="flex flex-wrap gap-2">
                @foreach($entry['aliases'] as $alias)
                    <span class="badge badge-default">{{ $alias }}</span>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if(!empty($entry['details']))
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Additional Details</h3>
        </div>
        <div class="card-body">
            <pre class="text-sm whitespace-pre-wrap">{{ is_array($entry['details']) ? json_encode($entry['details'], JSON_PRETTY_PRINT) : $entry['details'] }}</pre>
        </div>
    </div>
    @endif
</div>
@endsection
