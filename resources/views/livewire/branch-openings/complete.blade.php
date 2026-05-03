<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="mb-6">
                <span class="text-6xl">✓</span>
            </div>
            <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-4">Branch Opening Complete!</h1>
            <p class="text-gray-600 mb-6">The branch opening process has been completed successfully.</p>

            <div class="bg-gray-50 p-4 rounded mb-6 text-left">
                <h3 class="font-medium text-[var(--color-ink)] mb-2">Summary</h3>
                <p><strong>Branch:</strong> {{ $branch_name }}</p>
                <p><strong>Location:</strong> {{ $location }}</p>
                <p><strong>Scheduled Date:</strong> {{ $scheduled_date }}</p>
                <p><strong>Manager:</strong> {{ $manager_name }}</p>
                <p><strong>Staff Count:</strong> {{ count($staff_ids) }}</p>
                <p><strong>Counters:</strong> {{ $counter_count }}</p>
            </div>

            <div class="flex justify-center gap-4">
                <a href="{{ route('branch-openings.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">View All</a>
                <a href="{{ route('branches.index') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Go to Branches</a>
            </div>
        </div>
    </div>
</div>