<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('compliance.edd.index') }}" class="btn btn-ghost btn-icon">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></svg>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-semibold text-[--color-ink]">
                    {{ $record ? 'EDD Record: '.$record->edd_reference : 'New EDD Record' }}
                </h1>
                <p class="text-sm text-[--color-ink-muted]">
                    @if($record)
                        {{ $record->status->label() ?? 'Unknown Status' }}
                    @else
                        Create a new Enhanced Due Diligence record
                    @endif
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if($record)
                @if($record->status->value === 'Incomplete' || $record->status->value === 'PendingQuestionnaire')
                    <button type="button" wire:click="submitForReview()" class="btn btn-primary">
                        Submit for Review
                    </button>
                @endif
                @if($record->status->value === 'PendingReview')
                    @can('role:manager,compliance')
                        <button type="button" wire:click="$set('showRejectModal', true)" class="btn btn-danger">
                            Reject
                        </button>
                        <button type="button" wire:click="approve()" class="btn btn-success">
                            Approve
                        </button>
                    @endcan
                @endif
            @endif
        </div>
    </div>

    {{-- Error/Success Messages --}}
    @if(session('error'))
        <div class="alert alert-danger mb-6">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Step Indicator --}}
    @if(!$isReadOnly)
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center gap-4">
                    {{-- Step 1: Customer & Risk --}}
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors
                            {{ $currentStep >= 1 ? 'bg-[--color-accent] text-white' : 'bg-[--color-canvas-subtle] text-[--color-ink-muted]' }}">
                            @if($currentStep > 1)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            @else
                                1
                            @endif
                        </div>
                        <span class="text-sm font-medium {{ $currentStep >= 1 ? 'text-[--color-ink]' : 'text-[--color-ink-muted]' }}">Customer & Risk</span>
                    </div>

                    {{-- Connector --}}
                    <div class="w-16 h-0.5 {{ $currentStep > 1 ? 'bg-[--color-accent]' : 'bg-[--color-border]' }}"></div>

                    {{-- Step 2: Source of Funds --}}
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors
                            {{ $currentStep >= 2 ? 'bg-[--color-accent] text-white' : 'bg-[--color-canvas-subtle] text-[--color-ink-muted]' }}">
                            @if($currentStep > 2)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            @else
                                2
                            @endif
                        </div>
                        <span class="text-sm font-medium {{ $currentStep >= 2 ? 'text-[--color-ink]' : 'text-[--color-ink-muted]' }}">Source of Funds</span>
                    </div>

                    {{-- Connector --}}
                    <div class="w-16 h-0.5 {{ $currentStep > 2 ? 'bg-[--color-accent]' : 'bg-[--color-border]' }}"></div>

                    {{-- Step 3: Background & Review --}}
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors
                            {{ $currentStep >= 3 ? 'bg-[--color-accent] text-white' : 'bg-[--color-canvas-subtle] text-[--color-ink-muted]' }}">
                            3
                        </div>
                        <span class="text-sm font-medium {{ $currentStep >= 3 ? 'text-[--color-ink]' : 'text-[--color-ink-muted]' }}">Background & Review</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Form Content --}}
    <div class="max-w-4xl mx-auto">
        <div class="card">
            <div class="card-body">
                @switch($currentStep)
                    @case(1)
                        {{-- Step 1: Customer & Risk --}}
                        <div class="space-y-6">
                            <div class="form-group">
                                <label class="form-label required">Customer</label>
                                @if($record && $record->customer)
                                    <div class="flex items-center gap-3 p-4 bg-[--color-canvas-subtle] rounded-lg">
                                        <div class="w-10 h-10 bg-[--color-accent] rounded-lg flex items-center justify-center text-white font-semibold">
                                            {{ substr($record->customer->full_name, 0, 1) }}
                                        </div>
                                        <div>
                                            <p class="font-medium">{{ $record->customer->full_name }}</p>
                                            <p class="text-sm text-[--color-ink-muted]">{{ $record->customer->ic_number ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                @else
                                    <select wire:model="customerId" class="form-select" {{ $isReadOnly ? 'disabled' : '' }}>
                                        <option value="">Select customer...</option>
                                        @foreach($this->availableCustomers as $customer)
                                            <option value="{{ $customer['id'] }}">{{ $customer['full_name'] }} ({{ $customer['ic_number'] }})</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>

                            <div class="form-group">
                                <label class="form-label required">Risk Level</label>
                                <select wire:model="riskLevel" class="form-select" {{ $isReadOnly ? 'disabled' : '' }}>
                                    <option value="">Select risk level...</option>
                                    @foreach(\App\Enums\EddRiskLevel::cases() as $level)
                                        <option value="{{ $level->value }}">{{ $level->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">EDD Template</label>
                                <select wire:model="templateId" class="form-select" {{ $isReadOnly ? 'disabled' : '' }}>
                                    <option value="">Select template (optional)...</option>
                                    @foreach($this->availableTemplates as $template)
                                        <option value="{{ $template['id'] }}">{{ $template['name'] }} ({{ $template['type'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @break

                    @case(2)
                        {{-- Step 2: Source of Funds --}}
                        <div class="space-y-6">
                            <div class="form-group">
                                <label class="form-label required">Source of Funds</label>
                                <input type="text" wire:model="sourceOfFunds" class="form-input" placeholder="e.g., Salary, Business Revenue, Investment Returns" {{ $isReadOnly ? 'disabled' : '' }}>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Source of Funds Description</label>
                                <textarea wire:model="sourceOfFundsDescription" class="form-textarea" rows="3" placeholder="Provide additional details about the source of funds..." {{ $isReadOnly ? 'disabled' : '' }}></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label required">Purpose of Transaction</label>
                                <input type="text" wire:model="purposeOfTransaction" class="form-input" placeholder="e.g., Business payment, personal remittance" {{ $isReadOnly ? 'disabled' : '' }}>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Business Justification</label>
                                <textarea wire:model="businessJustification" class="form-textarea" rows="3" placeholder="Explain the business rationale for this transaction..." {{ $isReadOnly ? 'disabled' : '' }}></textarea>
                            </div>
                        </div>
                    @break

                    @case(3)
                        {{-- Step 3: Background & Review --}}
                        <div class="space-y-6">
                            <div class="form-group">
                                <label class="form-label">Employment Status</label>
                                <input type="text" wire:model="employmentStatus" class="form-input" placeholder="e.g., Employed, Self-Employed, Retired" {{ $isReadOnly ? 'disabled' : '' }}>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Employer Name</label>
                                <input type="text" wire:model="employerName" class="form-input" placeholder="Name of employer/company" {{ $isReadOnly ? 'disabled' : '' }}>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Employer Address</label>
                                <input type="text" wire:model="employerAddress" class="form-input" placeholder="Business address" {{ $isReadOnly ? 'disabled' : '' }}>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label class="form-label">Annual Income Range</label>
                                    <input type="text" wire:model="annualIncomeRange" class="form-input" placeholder="e.g., RM 50,000 - 100,000" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Estimated Net Worth</label>
                                    <input type="text" wire:model="estimatedNetWorth" class="form-input" placeholder="e.g., RM 500,000" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Source of Wealth</label>
                                <input type="text" wire:model="sourceOfWealth" class="form-input" placeholder="e.g., Inheritance, property sale, business equity" {{ $isReadOnly ? 'disabled' : '' }}>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Source of Wealth Description</label>
                                <textarea wire:model="sourceOfWealthDescription" class="form-textarea" rows="3" placeholder="Provide details about the source of wealth..." {{ $isReadOnly ? 'disabled' : '' }}></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Additional Information</label>
                                <textarea wire:model="additionalInformation" class="form-textarea" rows="4" placeholder="Any other relevant information..." {{ $isReadOnly ? 'disabled' : '' }}></textarea>
                            </div>
                        </div>
                    @break
                @endswitch
            </div>

            {{-- Navigation --}}
            @if(!$isReadOnly)
                <div class="card-footer flex items-center justify-between">
                    <div>
                        @if($currentStep > 1)
                            <button type="button" wire:click="previousStep" class="btn btn-secondary">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                                Back
                            </button>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        @if($currentStep < $maxSteps)
                            <button type="button" wire:click="nextStep" class="btn btn-primary">
                                Next
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        @else
                            <button type="button" wire:click="save" class="btn btn-primary">
                                Save Record
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Reject Modal --}}
    @if($showRejectModal ?? false)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" x-data="{ show: true }" x-show="show" x-on:click.self="show = false; $wire.showRejectModal = false">
        <div class="bg-[--color-surface] rounded-xl shadow-xl w-full max-w-md mx-4" x-show="show" x-on:click.stop>
            <div class="p-6 border-b border-[--color-border]">
                <h3 class="text-lg font-semibold">Reject EDD Record</h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="form-group">
                    <label class="form-label required">Rejection Reason</label>
                    <textarea wire:model="rejectReason" class="form-input" rows="3" placeholder="Enter the reason for rejection..."></textarea>
                </div>
            </div>
            <div class="p-6 border-t border-[--color-border] flex justify-end gap-3">
                <button type="button" wire:click="$set('showRejectModal', false)" class="btn btn-ghost">Cancel</button>
                <button type="button" wire:click="reject($rejectReason)" class="btn btn-danger" {{ !$rejectReason ? 'disabled' : '' }}>
                    Reject
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
