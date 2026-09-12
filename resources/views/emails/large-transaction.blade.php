@component('mail::message')
# Large Transaction Requires Approval

A large transaction has been submitted and requires manager approval.

## Transaction Details

**Transaction ID:** {{ $transaction->id }}
**Transaction Type:** {{ $transactionType }}
**Status:** {{ $transaction->status?->label() ?? 'N/A' }}
**Amount:** {{ $amount }}
**Currency:** {{ $currency }}
**Customer:** {{ $customer?->full_name ?? 'N/A' }}
**Branch:** {{ $branch }}
**Teller:** {{ $teller }}

@if($confirmation->id)
**Confirmation ID:** {{ $confirmation->id }}
@endif

{{-- The confirm route binds {transaction}; passing the confirmation id here
     would send the approver to the wrong (possibly another customer's)
     transaction page. Always pass the transaction id. --}}
@component('mail::button', ['url' => route('transactions.confirm.show', $confirmation->transaction_id)])
Review Transaction
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent