@extends('layouts.base')

@section('title', 'Chart of Accounts - CEMS-MY')

@section('content')
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Chart of Accounts</h1>
        <p class="text-sm text-gray-500">Ledger accounts grouped by type</p>
    </div>

    {{-- Account Groups --}}
    @foreach(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'] as $accountType)
        @if(!empty($accountsByType[$accountType]))
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title text-gray-900">{{ $accountType }}</h3>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th>Class</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accountsByType[$accountType] as $account)
                            <tr>
                                <td class="font-mono">{{ $account['account_code'] }}</td>
                                <td>{{ $account['account_name'] }}</td>
                                <td class="text-gray-500">{{ $account['account_class'] ?? 'N/A' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('accounting.ledger.account', $account['account_code']) }}"
                                       class="btn btn-ghost btn-sm">
                                        View Ledger
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach

    @if(empty($accountsByType['Asset']) && empty($accountsByType['Liability']) && empty($accountsByType['Equity']) && empty($accountsByType['Revenue']) && empty($accountsByType['Expense']))
        <div class="card">
            <div class="card-body text-center py-12">
                <p class="text-gray-500">No chart of accounts found</p>
            </div>
        </div>
    @endif
@endsection
