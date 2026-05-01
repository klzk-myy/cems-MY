@extends('layouts.base')

@section('title', 'New Journal Entry')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Create Journal Entry</h3></div>
        <div class="p-6">
            <form method="POST" action="{{ route('accounting.journal.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Date</label>
                    <input type="date" name="date" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Description</label>
                    <input type="text" name="description" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-[--color-ink] mb-1">Lines</label>
                    <div id="journal-lines">
                        <div class="flex gap-2 mb-2">
                            <select name="lines[0][account_code]" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg flex-1" required>
                                <option value="">Select account...</option>
                                @foreach($accounts ?? [] as $code => $name)
                                    <option value="{{ $code }}">{{ $code }} - {{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="lines[0][debit]" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg w-32" step="0.01" placeholder="Debit">
                            <input type="number" name="lines[0][credit]" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg w-32" step="0.01" placeholder="Credit">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="{{ route('accounting.journal') }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection