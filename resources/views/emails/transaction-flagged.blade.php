@component('mail::message')
# Transaction Flagged for Review

A transaction has been flagged and requires compliance review.

## Flagged Transaction Details

**Flag ID:** {{ $flaggedTransaction->id }}
**Flag Type:** {{ $flagType }}
**Flag Reason:** {{ $flagReason }}
**Status:** {{ $flaggedTransaction->status?->label() ?? 'N/A' }}

@if($customer)
**Customer:** {{ $customer->full_name ?? 'N/A' }}
@endif

@if($transaction)
**Transaction ID:** {{ $transaction->id }}
**Amount:** {{ $transaction->amount_local ?? 'N/A' }} {{ $transaction->currency_code ?? '' }}
@endif

@if($flaggedBy)
**Flagged By:** {{ $flaggedBy->username ?? $flaggedBy->full_name }}
@endif

@component('mail::button', ['url' => $url])
Review Flagged Transaction
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent