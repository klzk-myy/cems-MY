@component('mail::message')
# Compliance Case Assigned

A new compliance case has been assigned to you for review.

## Case Details

**Case ID:** {{ $complianceCase->id }}
**Case Number:** {{ $complianceCase->case_number }}
**Case Type:** {{ $caseType }}
**Priority:** {{ $priority }}
**Severity:** {{ $severity }}
**Customer:** {{ $customer?->full_name ?? 'N/A' }}

@if($assignedBy)
**Assigned By:** {{ $assignedBy->username ?? $assignedBy->full_name }}
@endif

@if($slaDeadline)
**SLA Deadline:** {{ $slaDeadline->format('Y-m-d H:i:s') }}
@endif

@if($daysUntilDeadline !== null)
**Days Remaining:** {{ $daysUntilDeadline }} day{{ $daysUntilDeadline !== 1 ? 's' : '' }}
@endif

@component('mail::button', ['url' => route('compliance.cases.show', $complianceCase->id)])
View Compliance Case
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent