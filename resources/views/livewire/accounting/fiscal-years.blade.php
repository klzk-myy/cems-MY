<div>
    {{-- Header --}}
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Fiscal Years</h1>
            <p class="text-sm text-gray-500">Manage annual fiscal years</p>
        </div>
        <button
            wire:click="$set('showCreateForm', 'yes')"
            class="btn btn-primary">
            Create Fiscal Year
        </button>
    </div>

    {{-- Create Form Modal --}}
    @if($showCreateForm === 'yes')
    <div class="modal-backdrop" wire:click="$set('showCreateForm', 'no')"></div>
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Create Fiscal Year</h3>
            <button wire:click="$set('showCreateForm', 'no')" class="modal-close">&times;</button>
        </div>
        <form wire:submit="createFiscalYear">
            <div class="modal-body">
                <div class="form-group">
                    <label for="newYearCode" class="form-label">Year Code</label>
                    <input
                        type="text"
                        id="newYearCode"
                        wire:model="newYearCode"
                        class="form-input"
                        placeholder="FY2026"
                        required
                        maxlength="10">
                    @error('newYearCode')
                        <span class="text-red-600 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="newStartDate" class="form-label">Start Date</label>
                    <input
                        type="date"
                        id="newStartDate"
                        wire:model="newStartDate"
                        class="form-input"
                        required>
                    @error('newStartDate')
                        <span class="text-red-600 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="newEndDate" class="form-label">End Date</label>
                    <input
                        type="date"
                        id="newEndDate"
                        wire:model="newEndDate"
                        class="form-input"
                        required>
                    @error('newEndDate')
                        <span class="text-red-600 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="$set('showCreateForm', 'no')" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Year Report Modal --}}
    @if($yearReport)
    <div class="modal-backdrop" wire:click="closeReport"></div>
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Fiscal Year Report - {{ $yearReport['year_code'] ?? 'N/A' }}</h3>
            <button wire:click="closeReport" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-white rounded">
                    <p class="text-sm text-gray-500">Total Revenue</p>
                    <p class="text-xl font-mono font-semibold">${{ number_format($yearReport['total_revenue'] ?? 0, 2) }}</p>
                </div>
                <div class="p-4 bg-white rounded">
                    <p class="text-sm text-gray-500">Total Expenses</p>
                    <p class="text-xl font-mono font-semibold">${{ number_format($yearReport['total_expenses'] ?? 0, 2) }}</p>
                </div>
                <div class="p-4 bg-white rounded col-span-2">
                    <p class="text-sm text-gray-500">Net Income</p>
                    <p class="text-2xl font-mono font-bold {{ ($yearReport['net_income'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        ${{ number_format($yearReport['net_income'] ?? 0, 2) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button wire:click="closeReport" class="btn btn-primary">Close</button>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Fiscal Years</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Year Code</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fiscalYears as $year)
                    <tr>
                        <td class="font-mono font-medium">{{ $year['year_code'] }}</td>
                        <td>{{ $year['start_date'] }}</td>
                        <td>{{ $year['end_date'] }}</td>
                        <td>
                            @if($year['is_closed'])
                                <span class="badge badge-default">Closed</span>
                            @else
                                <span class="badge badge-success">Open</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-2">
                                <button
                                    wire:click="viewReport('{{ $year['year_code'] }}')"
                                    class="btn btn-ghost btn-sm">
                                    Report
                                </button>
                                @if(!$year['is_closed'])
                                <button
                                    wire:click="$dispatch('confirm-close-year', { yearCode: '{{ $year['year_code'] }}' })"
                                    class="btn btn-ghost btn-sm text-red-600">
                                    Close
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">No fiscal years found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Close Year Confirmation Modal --}}
    <div
        x-data="{ show: false, yearCode: '', confirmCode: '' }"
        x-show="show"
        x-on:confirm-close-year.window="show = true; yearCode = $event.detail.yearCode; confirmCode = ''"
        x-on:close-modal.window="show = false"
        class="modal-backdrop"
        style="display: none;">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Close Fiscal Year</h3>
            </div>
            <form wire:submit="closeFiscalYear(yearCode, confirmCode)">
                <div class="modal-body">
                    <p class="text-gray-500 mb-4">Enter the fiscal year code to confirm: <strong class="font-mono" x-text="yearCode"></strong></p>
                    <div class="form-group">
                        <label class="form-label">Confirm Year Code</label>
                        <input
                            type="text"
                            x-model="confirmCode"
                            class="form-input"
                            placeholder="Enter year code"
                            required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" @click="show = false" class="btn btn-ghost">Cancel</button>
                    <button type="submit" class="btn btn-danger">Close Year</button>
                </div>
            </form>
        </div>
    </div>
</div>