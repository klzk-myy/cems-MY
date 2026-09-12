@component('mail::message')
# {{ $prefix }} {{ $appName }} System Alert

{{ $alert->message }}

## Alert Details

@if($alert->level)
**Level:** {{ $alert->level }}
@endif

@if($alert->source)
**Source:** {{ $alert->source }}
@endif

**Time:** {{ $alert->created_at->format('Y-m-d H:i:s') }}

@if($alert->metadata && is_array($alert->metadata) && count($alert->metadata) > 0)
**Metadata:**

@foreach($alert->metadata as $key => $value)
- **{{ ucfirst(str_replace('_', ' ', (string) $key)) }}:** {{ is_scalar($value) ? $value : json_encode($value) }}
@endforeach
@endif

@component('mail::button', ['url' => url('/system/alerts')])
View Alerts
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent