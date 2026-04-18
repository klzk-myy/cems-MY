# UI Feature: STR Studio Deadlines View

**Date:** 2026-04-17
**Status:** Approved
**Type:** Missing View (Bug Fix)

## Issue

`StrStudioController::deadlines()` method exists (line 114) but returns view `compliance.str-studio.deadlines` which does not exist.

## Design

Simple table view matching existing STR Studio pattern.

### Layout Structure

```blade
@extends('layouts.base')

@section('title', 'STR Filing Deadlines')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">STR Filing Deadlines</h1>
    <p class="text-sm text-[--color-ink-muted]">Track pending STR submissions and deadlines</p>
</div>
@endsection

@section('content')
{{-- Summary Cards --}}
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Overdue</p>
            <p class="text-2xl font-bold text-red-600">{{ $deadlines['overdue_count'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Upcoming (7 days)</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $deadlines['upcoming_count'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Urgent (24h)</p>
            <p class="text-2xl font-bold text-orange-600">{{ $deadlines['urgent_count'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Next Deadline</p>
            <p class="text-2xl font-bold">{{ $deadlines['next_deadline']?->format('d M Y') ?? 'N/A' }}</p>
        </div>
    </div>
</div>

{{-- Overdue Table --}}
@if($deadlines['overdue_count'] > 0)
<div class="card mb-6 border-l-4 border-red-500">
    <div class="card-header"><h3 class="card-title text-red-600">Overdue</h3></div>
    <table class="table">...</table>
</div>
@endif

{{-- Upcoming Table --}}
<div class="card">
    <div class="card-header"><h3 class="card-title">Upcoming Deadlines</h3></div>
    <table class="table">...</table>
</div>
@endsection
```

### Data Available

**From Controller (`$drafts`):**
- Collection of `StrDraft` models with `filing_deadline`

**From `$deadlines` array:**
- `overdue_count` - integer
- `upcoming_count` - integer
- `urgent_count` - integer (within 24 hours)
- `next_deadline` - Carbon datetime or null
- `overdue_reports` - collection of StrReport
- `upcoming_reports` - collection of StrReport

### Table Columns

| Column | Source | Notes |
|--------|--------|-------|
| Reference | `str_no` | Monospace font |
| Customer | `customer.name` | If loaded |
| Status | `status` | Badge |
| Filing Deadline | `filing_deadline` | Red if overdue |
| Actions | Link | View STR |

## Acceptance Criteria

1. Page loads without error
2. Summary cards display correct counts
3. Overdue items highlighted in red
4. Tables show deadline-sorted data
5. Matches existing STR Studio visual style
