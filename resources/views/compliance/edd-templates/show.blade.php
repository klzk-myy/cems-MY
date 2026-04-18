@extends('layouts.base')

@section('title', 'EDD Template: ' . ($template->name ?? ''))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ $template->name ?? 'Template' }}</h3>
        <a href="/compliance/edd-templates" class="btn btn-ghost btn-sm">Back</a>
    </div>
    <div class="card-body">
        <p class="text-[--color-ink-muted] mb-4">{{ $template->description ?? 'No description' }}</p>
        <h4 class="font-medium mb-3">Questions</h4>
        @forelse($template->questions ?? [] as $question)
            <div class="border-l-2 border-[--color-border] pl-4 mb-3">
                <p>{{ $question->question_text }}</p>
                <p class="text-xs text-[--color-ink-muted] mt-1">Type: {{ $question->question_type }}</p>
            </div>
        @empty
            <p class="text-[--color-ink-muted]">No questions in this template</p>
        @endforelse
    </div>
</div>
@endsection
