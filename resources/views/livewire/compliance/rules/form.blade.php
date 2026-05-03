<div class="max-w-3xl mx-auto">
    <form wire:submit="save" class="space-y-6">
        {{-- Basic Information --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Basic Information</h3>
            </div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Rule Code</label>
                        <input type="text" wire:model="ruleCode" class="form-input" placeholder="VEL-001" required>
                        @error('ruleCode') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rule Name</label>
                        <input type="text" wire:model="ruleName" class="form-input" placeholder="High Velocity Alert" required>
                        @error('ruleName') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea wire:model="description" class="form-textarea" rows="2" placeholder="Describe what this rule detects..."></textarea>
                    @error('description') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Rule Type</label>
                        <select wire:model="ruleType" class="form-select" required>
                            <option value="">Select type...</option>
                            @foreach($ruleTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }} - {{ $type->description() }}</option>
                            @endforeach
                        </select>
                        @error('ruleType') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Action</label>
                        <select wire:model="action" class="form-select" required>
                            <option value="">Select action...</option>
                            @foreach($actions as $act)
                                <option value="{{ $act }}">{{ ucfirst($act) }}</option>
                            @endforeach
                        </select>
                        @error('action') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Risk Score (0-100)</label>
                        <input type="number" wire:model="riskScore" class="form-input" min="0" max="100" required>
                        @error('riskScore') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div class="flex items-center h-full px-3">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="isActive" class="sr-only peer">
                                <div class="relative w-11 h-6 bg-gray-500 rounded-full peer peer-checked:bg-green-600 transition-colors">
                                    <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                                </div>
                                <span class="ml-3 text-sm text-gray-900">{{ $isActive ? 'Active' : 'Inactive' }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conditions Builder --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Conditions</h3>
                <div class="ml-auto">
                    <select wire:change="loadExample($event.target.value)" class="form-select text-sm">
                        <option value="">Load example...</option>
                        <option value="velocity">Velocity Rule</option>
                        <option value="structuring">Structuring Rule</option>
                        <option value="amount">Amount Threshold</option>
                        <option value="frequency">Frequency Rule</option>
                        <option value="geographic">Geographic Risk</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Conditions (JSON)</label>
                    <textarea wire:model="conditions" class="form-textarea font-mono text-sm" rows="10" placeholder='{"field": "value"}'></textarea>
                    @error('conditions') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    <p class="text-xs text-gray-500 mt-2">
                        Enter JSON conditions for rule evaluation. Select an example above to populate.
                    </p>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('compliance.rules.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">
                {{ $isEditing ? 'Update Rule' : 'Create Rule' }}
            </button>
        </div>
    </form>
</div>
