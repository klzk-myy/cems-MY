@component('mail::message')
# Monthly Revaluation Complete

The monthly revaluation for {{ now()->format('F Y') }} has been completed successfully.

@if(!empty($results))
## Summary

@if(array_key_exists('report_path', $results))
A detailed report has been generated and is attached to this email.
@endif

@if(is_array($results) && count($results) > 0)
@foreach($results as $key => $value)
@if(! in_array($key, ['report_path']))
**{{ ucfirst(str_replace('_', ' ', (string) $key)) }}:** {{ is_scalar($value) ? $value : json_encode($value) }}
@endif
@endforeach
@endif
@endif

Thank you,<br>
{{ config('app.name') }}
@endcomponent