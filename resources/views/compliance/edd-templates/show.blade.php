@extends('layouts.base')

@section('title', 'EDD Template - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">{{ $template->name }}</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">EDD Template</p>
    </div>
    <a href="{{ route('compliance.edd-templates.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        Back
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Template Details</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Type</span>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                    {{ str_replace('_', ' ', ucfirst($template->type)) }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Status</span>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $template->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            @if($template->description)
            <div class="pt-4 border-t border-[--color-border]">
                <p class="text-sm text-[--color-ink-muted] mb-2">Description</p>
                <p class="text-sm text-[--color-ink]">{{ $template->description }}</p>
            </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Questions</h3>
        </div>
        <div class="p-6">
            @if($template->questions && count($template->questions) > 0)
            <div class="space-y-4">
                @foreach($template->questions as $index => $question)
                <div class="py-3 border-b border-[--color-border] last:border-0">
                    <p class="text-sm font-medium text-[--color-ink]">{{ $index + 1 }}. {{ $question['text'] ?? 'Question' }}</p>
                    <p class="text-xs text-[--color-ink-muted] mt-1">Type: {{ $question['type'] ?? 'text' }}</p>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-[--color-ink-muted]">No questions defined</p>
            @endif
        </div>
    </div>
</div>
@endsection