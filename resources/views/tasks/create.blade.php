@extends('layouts.base')

@section('title', 'Create Task')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Create New Task</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('tasks.store') }}">
            @csrf
            <div class="space-y-6">
                <div>
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="form-label">Category</label>
                        <select name="category" class="form-input" required>
                            @foreach($categories ?? [] as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-input" required>
                            @foreach($priorities ?? [] as $priority)
                            <option value="{{ $priority }}">{{ $priority }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-input">
                    </div>
                </div>
                <div>
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-input" required>
                        <option value="">Select User</option>
                        @foreach($users ?? [] as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($relatedCustomerId)
                <input type="hidden" name="related_customer_id" value="{{ $relatedCustomerId }}">
                @endif
                @if($relatedTransactionId)
                <input type="hidden" name="related_transaction_id" value="{{ $relatedTransactionId }}">
                @endif
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Create Task</button>
                <a href="{{ route('tasks.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection