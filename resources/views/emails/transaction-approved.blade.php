@component('mail::message')
# Transaction Approved

The transaction has been approved successfully.

## Transaction Details

**Transaction ID:** {{ $transaction->id }}
**Customer:** {{ $customer->full_name ?? 'N/A' }}
**Amount:** {{ $transaction->amount_local }} {{ $transaction->currency_code }}
**Type:** {{ $transaction->type?->label() ?? 'N/A' }}
**Status:** {{ $transaction->status?->label() ?? 'N/A' }}
**Approved By:** {{ $transaction->approver?->full_name ?? 'N/A' }}

@component('mail::button', ['url' => $url])
View Transaction
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent