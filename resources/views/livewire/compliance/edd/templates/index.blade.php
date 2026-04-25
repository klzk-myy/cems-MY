@extends('layouts.base')

@section('title', 'EDD Templates')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">EDD Templates</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage Enhanced Due Diligence questionnaire templates</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="{{ route('compliance.edd.index') }}" class="btn btn-ghost">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to EDD
    </a>
    <button type="button" wire:click="openCreateModal()" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Template
    </button>
</div>
@endsection

@section('content')
{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <div class="flex flex-wrap gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="text" wire:model="search" class="form-input" placeholder="Template name...">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Type</label>
                <select wire:model="type" class="form-select">
                    <option value="">All Types</option>
                    @foreach($templateTypes as $templateType)
                        <option value="{{ $templateType->value }}">{{ $templateType->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

{{-- Templates Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Questions</th>
                    <th>Status</th>
                    <th>Usage</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $template)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center text-xs">
                                {{ substr($template->name, 0, 1) }}
                            </div>
                            <span class="font-medium">{{ $template->name }}</span>
                        </div>
                    </td>
                    <td>
                        <span class="text-sm">{{ $template->type->label() ?? 'Unknown' }}</span>
                    </td>
                    <td class="text-center">
                        {{ $template->getTotalQuestions() }}
                    </td>
                    <td>
                        @if($template->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <span class="text-sm text-[--color-ink-muted]">
                            {{ $template->enhanced_diligence_records_count ?? 0 }} records
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button type="button" wire:click="openEditModal({{ $template->id }})" class="btn btn-ghost btn-icon" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            <button type="button" wire:click="duplicate({{ $template->id }})" class="btn btn-ghost btn-icon" title="Duplicate">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                            <button type="button" wire:click="toggleActive({{ $template->id }})" class="btn btn-ghost btn-icon" title="{{ $template->is_active ? 'Deactivate' : 'Activate' }}">
                                @if($template->is_active)
                                    <svg class="w-4 h-4 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @endif
                            </button>
                            <button type="button" wire:click="delete({{ $template->id }})" class="btn btn-ghost btn-icon text-[--color-danger]" title="Delete" {{ $template->enhanced_diligence_records_count > 0 ? 'disabled' : '' }}>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No templates found</p>
                            <p class="empty-state-description">Create a new template to get started</p>
                            <button type="button" wire:click="openCreateModal()" class="btn btn-primary mt-4">New Template</button>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Create/Edit Modal --}}
@if($showModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" x-data="{ show: true }" x-show="show" x-on:click.self="show = false; $wire.closeModal()">
    <div class="bg-[--color-surface] rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" x-show="show" x-on:click.stop>
        <div class="p-6 border-b border-[--color-border]">
            <h3 class="text-lg font-semibold">{{ $editingTemplate ? 'Edit Template' : 'New Template' }}</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="form-group">
                <label class="form-label required">Template Name</label>
                <input type="text" wire:model="name" class="form-input" placeholder="e.g., PEP Enhanced Due Diligence">
            </div>

            <div class="form-group">
                <label class="form-label required">Template Type</label>
                <select wire:model="templateType" class="form-select">
                    <option value="">Select type...</option>
                    @foreach($templateTypes as $templateType)
                        <option value="{{ $templateType->value }}">{{ $templateType->label() }}</option>
                    @endforeach
                </select>
                @if($templateType)
                    <p class="text-sm text-[--color-ink-muted] mt-1">
                        {{ \App\Enums\EddTemplateType::tryFrom($templateType)?->description() ?? '' }}
                    </p>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea wire:model="description" class="form-input" rows="2" placeholder="Brief description of this template..."></textarea>
            </div>

            <div class="form-group">
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="isActive" class="rounded">
                    <span class="text-sm">Active (available for use)</span>
                </label>
            </div>

            <div class="border-t border-[--color-border] pt-4">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-medium">Questions</h4>
                    <button type="button" wire:click="addSection()" class="btn btn-ghost btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Section
                    </button>
                </div>

                @forelse($questions['sections'] as $sectionIndex => $section)
                    <div class="mb-4 p-4 bg-[--color-canvas-subtle] rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <input type="text" wire:model="questions.sections.{{ $sectionIndex }}.title" class="form-input flex-1" placeholder="Section title...">
                            <button type="button" wire:click="removeSection({{ $sectionIndex }})" class="btn btn-ghost btn-icon text-[--color-danger]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        @forelse($section['questions'] as $questionIndex => $question)
                            <div class="flex items-center gap-2 mb-2">
                                <input type="text" wire:model="questions.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.text" class="form-input flex-1" placeholder="Question text...">
                                <select wire:model="questions.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.type" class="form-select w-24">
                                    <option value="text">Text</option>
                                    <option value="textarea">Long Text</option>
                                    <option value="select">Select</option>
                                    <option value="checkbox">Yes/No</option>
                                </select>
                                <label class="flex items-center gap-1 text-xs">
                                    <input type="checkbox" wire:model="questions.sections.{{ $sectionIndex }}.questions.{{ $questionIndex }}.required">
                                    Required
                                </label>
                                <button type="button" wire:click="removeQuestion({{ $sectionIndex }}, {{ $questionIndex }})" class="btn btn-ghost btn-icon text-[--color-danger]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-[--color-ink-muted] py-2">No questions in this section</p>
                        @endforelse

                        <button type="button" wire:click="addQuestion({{ $sectionIndex }})" class="btn btn-ghost btn-sm mt-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Question
                        </button>
                    </div>
                @empty
                    <div class="text-center py-8 text-[--color-ink-muted]">
                        <p>No sections added yet. Click "Add Section" to begin.</p>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="p-6 border-t border-[--color-border] flex justify-end gap-3">
            <button type="button" wire:click="closeModal()" class="btn btn-ghost">Cancel</button>
            <button type="button" wire:click="save()" class="btn btn-primary">
                {{ $editingTemplate ? 'Update Template' : 'Create Template' }}
            </button>
        </div>
    </div>
</div>
@endif
