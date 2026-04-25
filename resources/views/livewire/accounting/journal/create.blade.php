<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[--color-ink]">New Journal Entry</h1>
        <p class="text-sm text-[--color-ink-muted]">Create a double-entry journal entry</p>
    </div>

    <form wire:submit.prevent="save">
        <div class="max-w-4xl mx-auto">
            {{-- Basic Info Card --}}
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">Entry Details</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Entry Date</label>
                            <input type="date" wire:model="entryDate" class="form-input @error('entryDate') form-input-error @enderror">
                            @error('entryDate')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="form-group md:col-span-2">
                            <label class="form-label">Description</label>
                            <input type="text" wire:model="description" class="form-input @error('description') form-input-error @enderror" placeholder="Enter journal entry description...">
                            @error('description')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Journal Lines Card --}}
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">Journal Lines</h3>
                    <button type="button" wire:click="addLine" class="btn btn-ghost btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Line
                    </button>
                </div>
                <div class="card-body">
                    {{-- Lines Table --}}
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th class="w-32">Debit (MYR)</th>
                                    <th class="w-32">Credit (MYR)</th>
                                    <th class="w-48">Description</th>
                                    <th class="w-16"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $index => $line)
                                <tr>
                                    <td>
                                        <select wire:model="lines.{{ $index }}.account_code" class="form-select @isset($lineErrors[$index]) form-input-error @endisset">
                                            <option value="">Select account...</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account['code'] }}">{{ $account['code'] }} - {{ $account['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @isset($lineErrors[$index])
                                            <p class="form-error text-xs mt-1">{{ $lineErrors[$index] }}</p>
                                        @endisset
                                    </td>
                                    <td>
                                        <input type="number" wire:model="lines.{{ $index }}.debit" class="form-input font-mono @isset($lineErrors[$index]) form-input-error @endisset" step="0.01" min="0" placeholder="0.00">
                                    </td>
                                    <td>
                                        <input type="number" wire:model="lines.{{ $index }}.credit" class="form-input font-mono @isset($lineErrors[$index]) form-input-error @endisset" step="0.01" min="0" placeholder="0.00">
                                    </td>
                                    <td>
                                        <input type="text" wire:model="lines.{{ $index }}.description" class="form-input" placeholder="Optional note...">
                                    </td>
                                    <td>
                                        @if(count($lines) > 2)
                                        <button type="button" wire:click="removeLine({{ $index }})" class="btn btn-ghost btn-icon text-[--color-danger]">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Totals --}}
                    <div class="mt-4 flex justify-end">
                        <div class="w-80">
                            <div class="flex justify-between py-2 border-b border-[--color-border]">
                                <span class="text-[--color-ink-muted]">Total Debits:</span>
                                <span class="font-mono font-medium">{{ number_format((float) $totalDebits, 2) }} MYR</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-[--color-border]">
                                <span class="text-[--color-ink-muted]">Total Credits:</span>
                                <span class="font-mono font-medium">{{ number_format((float) $totalCredits, 2) }} MYR</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-[--color-ink-muted]">Difference:</span>
                                <span class="font-mono font-medium {{ $isBalanced ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                                    {{ number_format((float) bcsub($totalDebits, $totalCredits, 4), 4) }} MYR
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Balance Status --}}
                    @error('balance')
                        <div class="mt-4 p-3 bg-[--color-danger]/10 border border-[--color-danger]/20 rounded">
                            <p class="text-[--color-danger] text-sm">{{ $message }}</p>
                        </div>
                    @enderror

                    @error('lines')
                        <div class="mt-4 p-3 bg-[--color-danger]/10 border border-[--color-danger]/20 rounded">
                            <p class="text-[--color-danger] text-sm">{{ $message }}</p>
                        </div>
                    @enderror

                    @if(!$isBalanced)
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="autoBalance" class="btn btn-secondary btn-sm">
                            Auto-Balance Entry
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('accounting.journal') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary" @if(!$isBalanced) disabled @endif>
                    Create Entry
                </button>
            </div>
        </div>
    </form>
</div>
