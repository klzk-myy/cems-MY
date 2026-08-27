<x-app-layout title="Bank Reconciliation">
    <x-page-header title="Bank Reconciliation" description="Reconcile bank transactions" />

    <x-card>
        <form method="POST" action="{{ route('accounting.reconciliation.import') }}" class="space-y-4" enctype="multipart/form-data">
            @csrf
            <x-input name="statement_file" label="Bank Statement" type="file" :required="true" />
            <x-checkbox name="auto_match" label="Auto-match transactions" :checked="true" />
            <x-textarea name="notes" label="Notes" />
            <div class="flex justify-end gap-3">
                <x-button type="submit" variant="primary">Import & Reconcile</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
