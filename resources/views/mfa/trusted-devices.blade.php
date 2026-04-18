@extends('layouts.base')

@section('title', 'Trusted Devices')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Trusted Devices</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Device</th>
                    <th>IP Address</th>
                    <th>Last Used</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($trustedDevices ?? [] as $device)
                <tr>
                    <td>{{ $device['name'] ?? 'Unknown Device' }}</td>
                    <td class="font-mono">{{ $device['ip_address'] ?? 'N/A' }}</td>
                    <td class="font-mono">{{ $device['last_used'] ?? 'N/A' }}</td>
                    <td>
                        <form method="POST" action="{{ route('mfa.trusted-devices.revoke', $device['id'] ?? 0) }}">
                            @csrf
                            <button type="submit" class="text-sm text-red-600 hover:underline">Revoke</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center py-8 text-[--color-ink-muted]">No trusted devices</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection