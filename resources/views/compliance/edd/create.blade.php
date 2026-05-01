@extends('layouts.base')

@section('title', 'New EDD Record')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Create EDD Record</h3>
        </div>
        <div class="p-6">
            <form method="POST" action="/compliance/edd">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Customer</label>
                    <select name="customer_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="">Select customer...</option>
                        @foreach($customers ?? [] as $c)
                            <option value="{{ $c->id }}">{{ $c->full_name }} ({{ $c->ic_number }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Risk Level</label>
                    <select name="risk_level" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Notes</label>
                    <textarea name="notes" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="4"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="/compliance/edd" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Cancel</a>
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
