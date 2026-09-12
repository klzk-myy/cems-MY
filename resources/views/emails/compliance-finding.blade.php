@component('mail::message')
# {{ $severity }} Compliance Finding: {{ $findingType }}

A new compliance finding has been generated and requires your attention.

## Finding Details

**Finding ID:** {{ $finding->id }}
**Type:** {{ $findingType }}
**Severity:** {{ $severity }}
**Generated At:** {{ $generatedAt->format('Y-m-d H:i:s') }}

@if($subjectInfo)
**Subject:** {{ $subjectInfo->name ?? $subjectInfo->full_name ?? $subjectInfo->case_number ?? get_class($subjectInfo) }}
@endif

@if(count($details) > 0)
## Details

@foreach($details as $key => $value)
- **{{ ucfirst(str_replace('_', ' ', (string) $key)) }}:** {{ is_scalar($value) ? $value : json_encode($value) }}
@endforeach
@endif

@component('mail::button', ['url' => $url])
View Finding
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent