<x-app-layout title="Create Journal Entry">
    <div class="space-y-6">
        <x-page-header title="Create Journal Entry" description="Create a new double-entry journal entry">
            <x-slot:actions>
                <x-button href="{{ route('accounting.journal') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <form action="{{ route('accounting.journal.store') }}" method="POST" class="space-y-6"
              x-data="{
                  lines: [
                      { account: '', description: '', debit: '', credit: '' },
                      { account: '', description: '', debit: '', credit: '' },
                  ],
                  addLine() {
                      this.lines.push({ account: '', description: '', debit: '', credit: '' });
                  },
                  removeLine(index) {
                      if (this.lines.length > 2) this.lines.splice(index, 1);
                  },
                  get totalDebit() {
                      return this.lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0).toFixed(2);
                  },
                  get totalCredit() {
                      return this.lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0).toFixed(2);
                  },
                  get difference() {
                      return (parseFloat(this.totalDebit) - parseFloat(this.totalCredit)).toFixed(2);
                  }
              }">
            @csrf

            <x-card class="space-y-6">
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
                                    <x-select
                                        :name="'lines[' + index + '][account]'"
                                        :options="[
                                            '1100-001' => '1100-001 - Cash MYR',
                                            '1100-002' => '1100-002 - Cash USD',
                                            '2100-001' => '2100-001 - Accounts Payable',
                                            '5100-001' => '5100-001 - Revenue',
                                        ]"
                                        placeholder="Select Account"
                                        x-model="line.account"
                                        inline
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <x-input :name="'lines[' + index + '][description]'" placeholder="Line description" x-model="line.description" inline />
                                </td>
                                <td class="px-4 py-3">
                                    <x-input type="number" :name="'lines[' + index + '][debit]'" step="0.01" min="0" placeholder="0.00" class="text-right" x-model="line.debit" inline />
                                </td>
                                <td class="px-4 py-3">
                                    <x-input type="number" :name="'lines[' + index + '][credit]'" step="0.01" min="0" placeholder="0.00" class="text-right" x-model="line.credit" inline />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-button type="button" variant="danger" size="sm" @click="removeLine(index)" :disabled="lines.length <= 2">Remove</x-button>
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
                        <p class="text-lg font-semibold" x-text="difference">0.00</p>
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
