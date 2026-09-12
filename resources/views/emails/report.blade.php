@component('mail::message')
# {{ $subject }}

Your report is ready and has been attached to this email.

Please find the attached file for the full details.

Thank you,<br>
{{ config('app.name') }}
@endcomponent