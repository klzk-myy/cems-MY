<x-app-layout title="Edit Branch">
    <div class="space-y-6">
        <x-page-header title="Edit Branch" description="Update {{ $branch->code }} details" />

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @if(session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif

        <x-card title="Branch Information" description="Update the branch details below">
            <form method="POST" action="{{ route('branches.update', $branch) }}">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-input name="code" label="Branch Code" value="{{ old('code', $branch->code) }}" required maxlength="20" />
                        <x-input name="name" label="Branch Name" value="{{ old('name', $branch->name) }}" required />

                        <x-select
                            name="type"
                            label="Type"
                            :options="$branchTypes ?? []"
                            :selected="old('type', $branch->type)"
                            required
                        />

                        <x-select
                            name="parent_id"
                            label="Parent Branch"
                            :options="($parentBranches ?? collect())->pluck('name', 'id')->toArray()"
                            placeholder="None (top-level)"
                            :selected="(string) old('parent_id', (string) $branch->parent_id)"
                        />

                        <x-input name="phone" label="Phone" value="{{ old('phone', $branch->phone) }}" maxlength="30" />
                        <x-input type="email" name="email" label="Email" value="{{ old('email', $branch->email) }}" />

                        <x-input name="address" label="Address" value="{{ old('address', $branch->address) }}" />
                        <x-input name="city" label="City" value="{{ old('city', $branch->city) }}" />
                        <x-input name="state" label="State" value="{{ old('state', $branch->state) }}" />
                        <x-input name="postal_code" label="Postal Code" value="{{ old('postal_code', $branch->postal_code) }}" />
                        <x-input name="country" label="Country" value="{{ old('country', $branch->country) }}" />

                        <x-checkbox name="is_active" label="Active" :checked="old('is_active', $branch->is_active)" />
                        <x-checkbox name="is_main" label="Main branch (head office)" :checked="old('is_main', $branch->is_main)" />
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-border flex items-center justify-end gap-3">
                    <x-button href="{{ route('branches.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Save Changes</x-button>
                </div>
            </form>
        </x-card>

        @unless($branch->is_main)
            <x-card title="Deactivate Branch" description="Mark this branch inactive and remove it from operational forms">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm text-ink-muted">
                        Attached resources: {{ $branch->users()->count() }} user(s),
                        {{ $branch->counters()->count() }} counter(s),
                        {{ $branch->tillBalances()->count() }} till balance(s).
                    </p>
                    <form action="{{ route('branches.deactivate', $branch) }}" method="POST"
                          data-confirm="Deactivate {{ $branch->code }}?">
                        @csrf
                        <x-button type="submit" variant="danger">Deactivate Branch</x-button>
                    </form>
                </div>
            </x-card>
        @endunless
    </div>
</x-app-layout>
