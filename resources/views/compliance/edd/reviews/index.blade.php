<x-app-layout title="EDD Reviews">
    <div class="space-y-6">
        <x-page-header title="EDD Reviews" description="Enhanced Due Diligence records awaiting review" />

        @if(session('success'))
            <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-700 dark:bg-green-900/30 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        <x-card>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                        <th class="py-2 pr-4">Reference</th>
                        <th class="py-2 pr-4">Customer</th>
                        <th class="py-2 pr-4">Risk Level</th>
                        <th class="py-2 pr-4">Submitted</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr class="border-b border-border/60">
                            <td class="py-3 pr-4 font-medium">
                                <a href="{{ route('compliance.edd-reviews.show', $record) }}" class="text-blue-600 hover:underline dark:text-blue-400">
                                    {{ $record->edd_reference }}
                                </a>
                            </td>
                            <td class="py-3 pr-4">
                                @if($record->customer)
                                    <x-customer-link :customer="$record->customer" :fallback="'Customer #'.$record->customer_id" />
                                @else
                                    <span class="text-ink-muted">—</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">{{ $record->risk_level?->value }}</td>
                            <td class="py-3 pr-4">{{ $record->questionnaire_completed_at?->format('d M Y') ?? '—' }}</td>
                            <td class="py-3 pr-4">
                                <x-badge variant="{{ match ($record->status) {
                                    App\Enums\EddStatus::Approved => 'success',
                                    App\Enums\EddStatus::Rejected, App\Enums\EddStatus::Expired => 'danger',
                                    App\Enums\EddStatus::QuestionnaireSubmitted, App\Enums\EddStatus::PendingReview => 'warning',
                                    default => 'gray',
                                } }}">{{ $record->status->label() }}</x-badge>
                            </td>
                            <td class="py-3 text-right">
                                <x-button href="{{ route('compliance.edd-reviews.show', $record) }}" variant="secondary" size="sm">Review</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-muted">No EDD records awaiting review.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <div class="mt-4">{{ $records->links() }}</div>
    </div>
</x-app-layout>
