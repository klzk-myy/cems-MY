@component('mail::message')
# Transaction Approved

The transaction has been approved successfully.

## Transaction Details

**Transaction ID:** {{ $transaction->id }}
**Customer:** {{ $customer->full_name ?? 'N/A' }}
**Amount:** {{ $transaction->amount_local }} {{ $transaction->currency_code }}
**Type:** {{ ucfirst($transaction->type->value) }}
**Status:** {{ ucfirst($transaction->status->value) }}
**Approved By:** {{ $transaction->approver?->full_name ?? 'N/A' }}

@component('mail::button', ['url' => $url])
View Transaction
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent