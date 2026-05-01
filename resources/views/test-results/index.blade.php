@extends('layouts.base')

@section('title', 'Test Results - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Test Results</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">System test and quality assurance</p>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Tests</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-[--color-ink-muted]">No test results available</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection