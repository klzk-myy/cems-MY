<x-app-layout title="Counter Transaction">
    <x-page-header title="Counter Transaction" description="Transaction management" />
    <x-card>
        <form method="POST" class="space-y-4">
            @csrf
            <x-textarea name="reason" label="Reason" :required="true" />
            <x-textarea name="notes" label="Additional Notes" />
            <div class="flex justify-end gap-3">
                <x-button type="submit" variant="primary">Submit</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
