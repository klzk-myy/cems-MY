@component('mail::message')
# [URGENT] Sanctions Match Detected

## IMPORTANT: Compliance Action Required

A potential sanctions match has been identified and requires immediate review.

## Match Details

**Sanction Entry ID:** {{ $sanctionEntry->id }}
**Screened Name:** <b>{{ $screenedName }}</b>
**Matched Name:** <b>{{ $matchedName }}</b>
**Match Score:** <b>{{ $matchScore }}</b>
**Match Type:** {{ $matchType }}
**Sanction List:** {{ $sanctionList?->name ?? 'N/A' }}
**Whitelisted:** {{ $isWhitelisted ? 'Yes' : 'No' }}
**Detected At:** {{ $createdAt->format('Y-m-d H:i:s') }}

@if($matchReason)
**Match Reason:** {{ $matchReason }}
@endif

@if(!$isWhitelisted)
## ACTION REQUIRED

This match is <b>NOT whitelisted</b> and must be reviewed immediately to determine if the party is subject to sanctions.
@endif

@component('mail::button', ['url' => route('compliance.sanctions.entries.show', $sanctionEntry->id)])
Review Sanctions Entry
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent