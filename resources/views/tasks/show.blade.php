@extends('layouts.base')

@section('title', 'Task Details')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">{{ $task->title ?? 'N/A' }}</h3>
        <a href="{{ route('tasks.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                <dd>
                    @if(isset($task->status))
                        @statuslabel($task->status)
                    @else
                        <span class="text-[--color-ink-muted]">N/A</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Priority</dt>
                <dd>
                    @if(isset($task->priority))
                        @statuslabel($task->priority)
                    @else
                        <span class="text-[--color-ink-muted]">N/A</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Assigned To</dt>
                <dd class="font-medium">{{ $task->assigned_to_name ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Due Date</dt>
                <dd class="font-mono">{{ $task->due_date ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Category</dt>
                <dd>{{ $task->category ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Created By</dt>
                <dd class="font-medium">{{ $task->created_by_name ?? 'N/A' }}</dd>
            </div>
        </div>

        <div class="mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-2">Description</h4>
            <p class="text-[--color-ink]">{{ $task->description ?? 'No description provided.' }}</p>
        </div>

        @if($task->related_customer_id)
        <div class="mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-2">Related Customer</h4>
            <a href="{{ route('customers.show', $task->related_customer_id) }}" class="text-primary hover:underline">
                View Customer #{{ $task->related_customer_id }}
            </a>
        </div>
        @endif

        <form method="POST" action="{{ route('tasks.update', $task->id ?? 0) }}" class="mt-6">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="pending" @if(($task->status ?? '') === 'pending') selected @endif>Pending</option>
                        <option value="in_progress" @if(($task->status ?? '') === 'in_progress') selected @endif>In Progress</option>
                        <option value="completed" @if(($task->status ?? '') === 'completed') selected @endif>Completed</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-input" rows="3">{{ $task->notes ?? '' }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update Task</button>
        </form>
    </div>
</div>
@endsection