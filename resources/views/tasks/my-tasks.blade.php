@extends('layouts.base')

@section('title', 'My Tasks')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">My Tasks</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks ?? [] as $task)
                <tr>
                    <td><a href="{{ route('tasks.show', $task->id) }}" class="text-primary hover:underline">{{ $task->title ?? 'N/A' }}</a></td>
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
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No tasks assigned to you</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection