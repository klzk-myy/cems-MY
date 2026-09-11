<x-email-layout title="Sanctions Match Notification">
    <p>This is an automated notification from CEMS regarding {{ str_replace('-', ' ', 'sanctions-match') }}.</p>
    <p><a href="{{ url('/') }}" style="display: inline-block; padding: 12px 24px; background-color: #0a0a0a; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500;">View in CEMS</a></p>
</x-email-layout>
