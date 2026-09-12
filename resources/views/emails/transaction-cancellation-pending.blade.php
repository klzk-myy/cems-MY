@component('mail::message')
# Transaction Cancellation Pending Approval

A cancellation request has been submitted for a transaction and is awaiting your approval.

## Transaction Details

**Transaction ID:** {{ $transaction->id }}
**Customer:** {{ $customer?->full_name ?? 'N/A' }}
**Amount:** {{ $transaction->amount_local ?? 'N/A' }} {{ $transaction->currency_code ?? '' }}
**Type:** {{ $transaction->transaction_type?->label() ?? $transaction->type?->label() ?? 'N/A' }}

## Cancellation Request

**Requested By:** {{ $requestedBy->username ?? $requestedBy->full_name ?? 'N/A' }}
**Reason:** {{ $reason }}

@component('mail::button', ['url' => $url])
Review Cancellation Request
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent