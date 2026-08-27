<x-email-layout title="{{ ucfirst(str_replace('-', ' ', large-transaction)) }} Notification">
    <p>This is an automated notification from CEMS regarding {{ str_replace('-', ' ', large-transaction) }}.</p>
    <p><a href="{{ url('/') }}" style="display: inline-block; padding: 12px 24px; background-color: #0a0a0a; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500;">View in CEMS</a></p>
</x-email-layout>
