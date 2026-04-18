@extends('layouts.base')

@section('title', 'New Journal Entry')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Create Journal Entry</h3></div>
        <div class="card-body">
            <form method="POST" action="/accounting/journal">
                @csrf
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-input" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Lines</label>
                    <div id="journal-lines">
                        <div class="flex gap-2 mb-2">
                            <select name="lines[0][account_code]" class="form-select flex-1" required>
                                <option value="">Select account...</option>
                                @foreach($accounts ?? [] as $code => $name)
                                    <option value="{{ $code }}">{{ $code }} - {{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="lines[0][debit]" class="form-input w-32" step="0.01" placeholder="Debit">
                            <input type="number" name="lines[0][credit]" class="form-input w-32" step="0.01" placeholder="Credit">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="/accounting/journal" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
