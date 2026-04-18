@extends('layouts.base')

@section('title', 'Overdue Tasks')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Overdue Tasks</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Assigned To</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks ?? [] as $task)
                <tr class="@if($task->is_overdue) text-red-600 @endif">
                    <td><a href="{{ route('tasks.show', $task->id) }}" class="text-primary hover:underline">{{ $task->title ?? 'N/A' }}</a></td>
                    <td>{{ $task->assigned_to_name ?? 'N/A' }}</td>
                    <td>{{ $task->category ?? 'N/A' }}</td>
                    <td>
                        @if(isset($task->priority))
                            @statuslabel($task->priority)
                        @else
                            <span class="text-[--color-ink-muted]">N/A</span>
                        @endif
                    </td>
                    <td class="font-mono">{{ $task->due_date ?? '-' }}</td>
                    <td>
                        @if(isset($task->status))
                            @statuslabel($task->status)
                        @else
                            <span class="text-[--color-ink-muted]">N/A</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No overdue tasks</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection