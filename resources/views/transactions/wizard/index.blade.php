@extends('layouts.base')

@section('title', 'Transaction Wizard')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Transaction Wizard</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Create a new currency exchange transaction</p>
    </div>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink-muted]">Transaction wizard step selection</p>
    </div>
</div>
@endsection