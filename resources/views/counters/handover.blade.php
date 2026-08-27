<x-app-layout title="{{ ucfirst(str_replace('-', ' ', $view)) }} Counter">
    <x-page-header title="{{ ucfirst(str_replace('-', ' ', $view)) }} Counter" description="Counter management" />
    <x-card>
        <form method="POST" class="space-y-4">
            @csrf
            <x-input name="balance" label="Balance" type="number" :required="true" />
            <x-textarea name="notes" label="Notes" :required="true" />
            <div class="flex justify-end gap-3">
                <x-button type="submit" variant="primary">Submit</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
