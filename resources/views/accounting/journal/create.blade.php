<x-app-layout title="Create Journal Entry">
    <div class="space-y-6">
        <x-page-header title="Create Journal Entry" description="Create a new double-entry journal entry">
            <x-slot:actions>
                <x-button href="{{ route('accounting.journal') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <form action="{{ route('accounting.journal.store') }}" method="POST" class="space-y-6">
            @csrf

            <x-card>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-input type="date" name="date" label="Entry Date" value="{{ date('Y-m-d') }}" required />
                        <x-input name="reference" label="Reference" placeholder="JE-0001" />
                        <x-select
                            name="status"
                            label="Status"
                            :options="['draft' => 'Draft', 'pending' => 'Pending', 'posted' => 'Posted']"
                            selected="draft"
                        />
                    </div>

                    <x-input name="description" label="Description" placeholder="Enter journal entry description" required />
                </div>
            </x-card>

            <x-card>
                <div class="px-4 py-3 border-b border-[#e5e5e5] bg-canvas-subtle">
                    <h3 class="text-sm font-medium text-ink">Journal Lines</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-canvas-subtle border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="journal-lines" class="divide-y divide-[#e5e5e5]">
                        <tr>
                            <td class="px-4 py-3">
                                <x-select
                                    name="lines[0][account]"
                                    :options="[
                                        '1100-001' => '1100-001 - Cash MYR',
                                        '1100-002' => '1100-002 - Cash USD',
                                        '2100-001' => '2100-001 - Accounts Payable',
                                        '5100-001' => '5100-001 - Revenue',
                                    ]"
                                    placeholder="Select Account"
                                    :inline="true"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <x-input name="lines[0][description]" placeholder="Line description" :inline="true" />
                            </td>
                            <td class="px-4 py-3">
                                <x-input type="number" name="lines[0][debit]" step="0.01" min="0" placeholder="0.00" class="text-right" :inline="true" />
                            </td>
                            <td class="px-4 py-3">
                                <x-input type="number" name="lines[0][credit]" step="0.01" min="0" placeholder="0.00" class="text-right" :inline="true" />
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-button variant="ghost" size="sm" class="text-red-600 hover:text-red-800">Remove</x-button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3">
                                <x-select
                                    name="lines[1][account]"
                                    :options="[
                                        '1100-001' => '1100-001 - Cash MYR',
                                        '1100-002' => '1100-002 - Cash USD',
                                        '2100-001' => '2100-001 - Accounts Payable',
                                        '5100-001' => '5100-001 - Revenue',
                                    ]"
                                    placeholder="Select Account"
                                    :inline="true"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <x-input name="lines[1][description]" placeholder="Line description" :inline="true" />
                            </td>
                            <td class="px-4 py-3">
                                <x-input type="number" name="lines[1][debit]" step="0.01" min="0" placeholder="0.00" class="text-right" :inline="true" />
                            </td>
                            <td class="px-4 py-3">
                                <x-input type="number" name="lines[1][credit]" step="0.01" min="0" placeholder="0.00" class="text-right" :inline="true" />
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-button variant="ghost" size="sm" class="text-red-600 hover:text-red-800">Remove</x-button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-[#e5e5e5]">
                    <x-button type="button" id="add-line" variant="secondary">+ Add Line</x-button>
                </div>
            </x-card>

            <x-card class="p-4">
                <div class="flex justify-end gap-8">
                    <div class="text-right">
                        <p class="text-sm text-ink-muted">Total Debit</p>
                        <p class="text-lg font-semibold" id="total-debit">0.00</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-ink-muted">Total Credit</p>
                        <p class="text-lg font-semibold" id="total-credit">0.00</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-ink-muted">Difference</p>
                        <p class="text-lg font-semibold" id="difference">0.00</p>
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <x-button type="button" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Create Entry</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
