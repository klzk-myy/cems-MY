@extends('layouts.base')

@section('title', 'Trusted Devices - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Trusted Devices</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Manage devices that bypass 2FA</p>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink-muted]">No trusted devices registered.</p>
    </div>
</div>
@endsection