@extends('layouts.base')

@section('title', 'Tasks')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Tasks</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage your assigned tasks</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="/tasks/create" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Create Task
    </a>
</div>
@endsection

@section('content')
{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <div class="flex gap-2">
            <a href="/tasks" class="btn {{ !request('filter') ? 'btn-primary' : 'btn-ghost' }}">All</a>
            <a href="/tasks?filter=my" class="btn {{ request('filter') === 'my' ? 'btn-primary' : 'btn-ghost' }}">My Tasks</a>
            <a href="/tasks?filter=pending" class="btn {{ request('filter') === 'pending' ? 'btn-primary' : 'btn-ghost' }}">Pending</a>
            <a href="/tasks/overdue" class="btn {{ request()->is('tasks/overdue') ? 'btn-primary' : 'btn-ghost' }}">Overdue</a>
        </div>
    </div>
</div>

{{-- Tasks List --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks ?? [] as $task)
                <tr>
                    <td>
                        <div>
                            <p class="font-medium">{{ $task->title }}</p>
                            <p class="text-xs text-[--color-ink-muted]">{{ Str::limit($task->description, 50) }}</p>
                        </div>
                    </td>
                    <td>
                        @php
                            $priorityClass = match($task->priority->value ?? '') {
                                'Critical' => 'badge-danger',
                                'High' => 'badge-warning',
                                'Medium' => 'badge-info',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $priorityClass }}">{{ $task->priority->label() ?? 'Low' }}</span>
                    </td>
                    <td class="{{ $task->isOverdue() ? 'text-[--color-danger]' : '' }}">
                        {{ $task->due_date?->format('d M Y') ?? 'No due date' }}
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-[--color-canvas-subtle] rounded flex items-center justify-center text-xs">
                                {{ substr($task->assignee->username ?? '?', 0, 1) }}
                            </div>
                            <span class="text-sm">{{ $task->assignee->username ?? 'Unassigned' }}</span>
                        </div>
                    </td>
                    <td>
                        @php
                            $statusClass = match($task->status->value ?? '') {
                                'Completed' => 'badge-success',
                                'InProgress' => 'badge-info',
                                'Pending' => 'badge-warning',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $task->status->label() ?? 'Open' }}</span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="/tasks/{{ $task->id }}" class="btn btn-ghost btn-icon">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No tasks found</p>
                            <p class="empty-state-description">Create a task to get started</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
