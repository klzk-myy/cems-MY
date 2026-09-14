<x-app-layout title="Create Journal Entry">
    <div class="space-y-6">
        <x-page-header title="Create Journal Entry" description="Create a new double-entry journal entry">
            <x-slot:actions>
                <x-button href="{{ route('accounting.journal') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <form action="{{ route('accounting.journal.store') }}" method="POST" class="space-y-6"
              x-data="journalCreate"
              data-accounts='@json($accounts->map(fn ($a) => ["code" => $a->account_code, "label" => $a->account_code." - ".$a->account_name])->values())'>
            @csrf

            @if ($errors->any())
                <x-alert variant="danger">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <x-card class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input type="date" name="entry_date" label="Entry Date" value="{{ old('entry_date', date('Y-m-d')) }}" required />
                    @if (auth()->user()->isAdmin())
                        <x-select
                            name="branch_id"
                            label="Branch"
                            :options="$branches ?? []"
                            placeholder="Company-wide"
                        />
                    @endif
                </div>

                <x-input name="description" label="Description" placeholder="Enter journal entry description" value="{{ old('description') }}" required />
            </x-card>

            <x-card title="Journal Lines">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Remove</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        <template x-for="(line, index) in lines" :key="index">
                            <tr>
                                <td class="px-4 py-3">
                                    <select
                                        :name="'lines[' + index + '][account_code]'"
                                        x-model="line.account"
                                        required
                                        class="w-full rounded-md border-border bg-surface text-ink text-sm focus:border-primary focus:ring-primary"
                                    >
                                        <option value="">Select Account</option>
                                        <template x-for="acc in accounts" :key="acc.code">
                                            <option :value="acc.code" x-text="acc.label"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" :name="'lines[' + index + '][description]'" placeholder="Line description" x-model="line.description"
                                           class="w-full rounded-md border-border bg-surface text-ink text-sm focus:border-primary focus:ring-primary" />
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" :name="'lines[' + index + '][debit]'" step="0.01" min="0" placeholder="0.00" x-model="line.debit" required
                                           class="w-full rounded-md border-border bg-surface text-ink text-sm text-right focus:border-primary focus:ring-primary" />
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" :name="'lines[' + index + '][credit]'" step="0.01" min="0" placeholder="0.00" x-model="line.credit" required
                                           class="w-full rounded-md border-border bg-surface text-ink text-sm text-right focus:border-primary focus:ring-primary" />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-button type="button" variant="danger" size="sm" @click="removeLine(index)" x-bind:disabled="lines.length <= 2">Remove</x-button>
                                </td>
                            </tr>
                        </template>
                    </x-slot:tbody>
                </x-table>
                <div class="px-4 py-3 border-t border-border">
                    <x-button type="button" @click="addLine()" variant="secondary">+ Add Line</x-button>
                </div>
            </x-card>

            <x-card class="space-y-6">
                <div class="flex justify-end gap-8">
                    <div class="text-right">
                        <p class="text-sm text-ink-muted">Total Debit</p>
                        <p class="text-lg font-semibold" x-text="totalDebit">0.00</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-ink-muted">Total Credit</p>
                        <p class="text-lg font-semibold" x-text="totalCredit">0.00</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-ink-muted">Difference</p>
                        <p class="text-lg font-semibold" :class="balanced ? 'text-success-text' : 'text-danger-text'" x-text="difference">0.00</p>
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <x-button href="{{ route('accounting.journal') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary" x-bind:disabled="!balanced">Create Entry</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
