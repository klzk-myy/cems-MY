<x-app-layout title="Role Permissions">
    <div class="space-y-6">
        <x-page-header title="Role Permissions" description="Revoke or restore each role's built-in capabilities. This matrix can only narrow what a role is allowed to do — it can never grant a capability the role does not have. Administrator always retains full access." />

        <x-card>
            <form method="POST" action="{{ route('admin.role-permissions.update') }}">
                @csrf
                <div class="space-y-6">
                    @foreach ($permissionsByCategory as $category => $categoryPermissions)
                        <div>
                            <h3 class="text-sm font-semibold text-ink mb-3 pb-2 border-b border-border">{{ $category }}</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                            <th class="px-4 py-3 text-left">Permission</th>
                                            @foreach ($roles as $role)
                                                <th class="px-4 py-3 text-center">{{ $role->label() }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($categoryPermissions as $permission)
                                            <tr class="border-t border-border hover:bg-canvas-subtle">
                                                <td class="px-4 py-3">
                                                    <div class="text-sm font-medium text-ink">{{ $permission->label() }}</div>
                                                    <div class="text-xs text-ink-muted mt-0.5">{{ $permission->description() }}</div>
                                                </td>
                                                @foreach ($roles as $role)
                                                    @php
                                                        $granted = $matrix[$role->value][$permission->value] ?? false;
                                                        $checkboxName = "permissions.{$role->value}.{$permission->value}";
                                                        $withinCeiling = $role->staticallyGrants($permission);
                                                        $isAdminLocked = $role === \App\Enums\UserRole::Admin;
                                                    @endphp
                                                    <td class="px-4 py-3 text-center">
                                                        @if (! $withinCeiling)
                                                            <span
                                                                class="inline-flex items-center justify-center text-ink-muted"
                                                                title="Outside {{ $role->label() }}'s built-in capabilities"
                                                            >—</span>
                                                        @else
                                                            <label class="inline-flex items-center justify-center cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    name="{{ $checkboxName }}"
                                                                    value="1"
                                                                    @if($granted) checked @endif
                                                                    @if($isAdminLocked) disabled checked @endif
                                                                    class="w-4 h-4 rounded bg-canvas-subtle border-border text-primary focus:ring-primary focus:ring-2 disabled:opacity-50"
                                                                >
                                                                @if($isAdminLocked)
                                                                    <input type="hidden" name="{{ $checkboxName }}" value="1">
                                                                @endif
                                                            </label>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    <div class="px-5 py-3 border-t border-border flex items-center justify-end gap-3">
                        <x-button href="{{ route('admin.role-permissions.index') }}" variant="secondary">Reset</x-button>
                        <x-button type="submit" variant="primary">Save Permissions</x-button>
                    </div>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
