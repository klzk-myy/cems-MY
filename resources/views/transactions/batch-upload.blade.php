<x-app-layout title="Batch Upload">
    <x-page-header title="Batch Upload" description="Upload multiple transactions" />
    <x-card>
        <form method="POST" action="{{ route('transactions.batch-upload.store') }}" class="space-y-4" enctype="multipart/form-data">
            @csrf
            <x-input name="file" label="Upload File" type="file" :required="true" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('transactions.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Upload</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
