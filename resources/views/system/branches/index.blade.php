<x-app-layout title="Branch Management">
    <div class="space-y-6">
        <x-page-header title="Branches" description="Manage branch registry, status and attached resources">
            <x-slot:actions>
                <x-button href="{{ route('branches.create') }}" variant="primary">Add Branch</x-button>
            </x-slot:actions>
        </x-page-header>

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @if(session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Users</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Counters</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Tills</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase"></th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($branches ?? [] as $branch)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 font-mono text-sm text-ink">{{ $branch->code }}</td>
                            <td class="px-4 py-3 text-sm text-ink">
                                {{ $branch->name }}
                                @if($branch->is_main)
                                    <x-badge variant="purple" size="sm">HQ</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ ucwords(str_replace('_', ' ', $branch->type)) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink">{{ $branch->users_count }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink">{{ $branch->counters_count }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink">{{ $branch->till_balances_count }}</td>
                            <td class="px-4 py-3">
                                @if($branch->is_active)
                                    <x-badge variant="success">Active</x-badge>
                                @else
                                    <x-badge variant="gray">Inactive</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2" x-data="branchModals">
                                    <x-button href="{{ route('branches.edit', $branch) }}" variant="ghost" size="sm">Edit</x-button>
                                    @if($branch->is_active && ! $branch->is_main)
                                        <x-button @click="deactivateModal = true" variant="danger" size="sm">Deactivate</x-button>

                                        <div x-show="deactivateModal"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-150"
                                             x-transition:leave-start="opacity-100 scale-100"
                                             x-transition:leave-end="opacity-0 scale-95"
                                             @keydown.escape.window="deactivateModal = false"
                                             @click="deactivateModal = false"
                                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                                             role="dialog"
                                             aria-modal="true"
                                             aria-labelledby="deactivate-modal-title-{{ $branch->id }}">
                                            <div class="bg-surface rounded-xl shadow-lg max-w-md w-full mx-4" @click.stop>
                                                <div class="flex items-center justify-between px-5 py-3 border-b border-border">
                                                    <h3 id="deactivate-modal-title-{{ $branch->id }}" class="text-lg font-semibold text-ink">Deactivate {{ $branch->code }}?</h3>
                                                    <button @click="deactivateModal = false" class="text-ink-muted hover:text-ink p-1" aria-label="Close">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                <form action="{{ route('branches.deactivate', $branch) }}" method="POST">
                                                    @csrf
                                                    <div class="p-6 space-y-4">
                                                        <p class="text-sm text-ink-muted">
                                                            This branch will be marked inactive and removed from operational forms.
                                                            Attached resources:
                                                        </p>
                                                        <ul class="text-sm text-ink space-y-1">
                                                            <li>{{ $branch->users_count }} user(s)</li>
                                                            <li>{{ $branch->counters_count }} counter(s)</li>
                                                            <li>{{ $branch->till_balances_count }} till balance(s)</li>
                                                        </ul>
                                                    </div>
                                                    <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-border">
                                                        <x-button type="button" @click="deactivateModal = false" variant="secondary">Back</x-button>
                                                        <x-button type="submit" variant="danger">Confirm Deactivate</x-button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No branches found." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="mt-4">
            {{ $branches->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>
