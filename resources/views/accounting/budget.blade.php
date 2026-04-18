@extends('layouts.base')

@section('title', 'Budget')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Budget vs Actual</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Budget</th>
                    <th class="text-right">Actual</th>
                    <th class="text-right">Variance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['items'] ?? [] as $item)
                <tr>
                    <td>{{ $item['account_name'] }}</td>
                    <td class="font-mono text-right">{{ number_format($item['budget'], 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($item['actual'], 2) }}</td>
                    <td class="font-mono text-right {{ $item['variance'] >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                        {{ number_format($item['variance'], 2) }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center py-8 text-[--color-ink-muted]">No budget data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
