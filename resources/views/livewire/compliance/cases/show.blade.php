<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('compliance.cases.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Cases</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Case {{ $case->case_number }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Type</label>
                    <p class="mt-1">{{ $case->type }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Status</label>
                    <p class="mt-1">
                        @if($case->status === 'open')
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">Open</span>
                        @elseif($case->status === 'investigating')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Investigating</span>
                        @else
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">{{ ucfirst($case->status) }}</span>
                        @endif
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Priority</label>
                    <p class="mt-1">
                        @if($case->priority === 'high')
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded">High</span>
                        @elseif($case->priority === 'medium')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Medium</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded">Low</span>
                        @endif
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Customer</label>
                    <p class="mt-1">{{ $case->customer_name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Created</label>
                    <p class="mt-1">{{ $case->created_at->format('Y-m-d') }}</p>
                </div>

                @if($case->assigned_to)
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Assigned To</label>
                    <p class="mt-1">{{ $case->assignedTo->name }}</p>
                </div>
                @endif
            </div>

            @if($case->description)
            <div class="mt-6">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                <p class="mt-1">{{ $case->description }}</p>
            </div>
            @endif

            <div class="flex justify-end gap-4 mt-6">
                <button wire:click="addNote" class="px-4 py-2 border border-[var(--color-border)] rounded">Add Note</button>
                @if($case->status === 'open')
                <button wire:click="startInvestigation" class="px-4 py-2 bg-blue-600 text-white rounded">Start Investigation</button>
                @endif
            </div>
        </div>

        @if($case->notes->count() > 0)
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h3 class="font-medium text-[var(--color-ink)] mb-4">Notes</h3>
            @foreach($case->notes as $note)
            <div class="border-b border-[var(--color-border)] pb-4 mb-4 last:border-0">
                <p class="text-sm">{{ $note->content }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $note->created_at->format('Y-m-d H:i') }} - {{ $note->user->name }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>