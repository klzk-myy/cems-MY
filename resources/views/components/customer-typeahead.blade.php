@props([
    'name' => 'customer_id',
    'label' => 'Customer',
    'customers' => [],
    'required' => true,
])

@php
$errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
$hasError = $errors->has($name);
$initialId = old($name);
$initialName = ($initialId !== null && $initialId !== '') ? ($customers[$initialId] ?? null) : null;
@endphp

<div class="mb-4"
     x-data="customerTypeahead"
     data-initial-name="{{ $initialName ?? '' }}"
     data-initial-id="{{ $initialId ?? '' }}"
     data-search-url="{{ route('customers.search') }}"
     data-register-url="{{ route('customers.quick-create') }}"
     @click.outside="open = false">
    <label for="{{ $name }}_search" class="block text-sm font-medium text-ink">
        {{ $label }}
        @if($required) <span class="text-danger">*</span> @endif
    </label>

    <div class="relative">
        <input type="text"
               id="{{ $name }}_search"
               x-model="query"
               @input.debounce.400ms="search()"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.enter="enter($event)"
               @keydown.escape="open = false"
               @focus="focus()"
               autocomplete="off"
               placeholder="Type customer name or IC number…"
               role="combobox"
               :aria-expanded="open"
               aria-controls="{{ $name }}_results"
               aria-autocomplete="list"
               @if($hasError) aria-describedby="{{ $name }}-error" @endif
               class="mt-1 w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg
                      focus:bg-surface text-ink placeholder:text-ink-muted
                      focus:outline-none focus:ring-2 focus:ring-primary {{ $hasError ? 'border-danger' : '' }}">
        <input type="hidden" name="{{ $name }}" :value="selectedId">

        <div x-show="loading" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-ink-muted">…</div>

        <ul x-show="open" x-cloak id="{{ $name }}_results" role="listbox"
            class="absolute z-20 mt-1 w-full max-h-64 overflow-auto bg-surface border border-border rounded-lg shadow-lg">
            <template x-for="(c, i) in results" :key="c.id">
                <li role="option"
                    @mousedown.prevent="select(c)"
                    @mouseenter="active = i"
                    :class="{ 'bg-canvas-subtle': i === active }"
                    class="px-3 py-2 text-sm cursor-pointer flex items-center justify-between gap-2">
                    <span>
                        <span class="font-medium text-ink" x-text="c.full_name"></span>
                        <span class="text-ink-muted" x-text="c.id_number ? ' · ' + c.id_number : ''"></span>
                    </span>
                    <span class="flex items-center gap-2 shrink-0">
                        <span x-show="c.is_sanctioned" class="text-xs font-medium text-danger">SANCTION</span>
                        <span x-show="c.is_pep" class="text-xs font-medium text-warning">PEP</span>
                        <span class="text-xs text-ink-muted" x-text="c.risk_rating"></span>
                    </span>
                </li>
            </template>
            <li x-show="! loading && results.length === 0" class="px-3 py-2 text-sm text-ink-muted">
                No customers found
            </li>
            <li @mousedown.prevent="openRegister()"
                @mouseenter="active = -1"
                :class="{ 'bg-canvas-subtle': active === -1 }"
                class="px-3 py-2 text-sm cursor-pointer border-t border-border font-medium text-primary">
                + Register new customer<span x-show="query.trim()" x-text="': ' + query.trim()"></span>
            </li>
        </ul>
    </div>

    <template x-if="selected">
        <div class="mt-2 rounded-lg border border-border bg-canvas-subtle px-3 py-2 text-xs flex flex-wrap items-center gap-x-4 gap-y-1">
            <a class="font-medium text-primary hover:underline" :href="'{{ url('/customers') }}/' + selected.id" x-text="selected.full_name"></a>
            <span><span class="text-ink-muted">ID:</span> <span x-text="selected.id_number || '—'"></span></span>
            <span><span class="text-ink-muted">Nationality:</span> <span x-text="selected.nationality || '—'"></span></span>
            <span><span class="text-ink-muted">Risk:</span> <span x-text="selected.risk_rating || '—'"></span></span>
            <span x-show="selected.cdd_level"><span class="text-ink-muted">CDD:</span> <span x-text="selected.cdd_level"></span></span>
            <span x-show="selected.is_pep" class="font-medium text-warning">PEP</span>
            <span x-show="selected.is_sanctioned" class="font-medium text-danger">SANCTION</span>
            <span x-show="regExisting" class="text-ink-muted">(existing record)</span>
        </div>
    </template>

    <template x-if="registering">
        {{-- Enter must not bubble to the parent transaction form --}}
        <div class="mt-2 rounded-lg border border-border bg-surface p-3" @keydown.enter.prevent="register()">
            <p class="text-xs font-medium text-ink mb-2">Register new customer</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div>
                    <input type="text" x-model="reg.full_name" placeholder="Full name *"
                           class="w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg focus:bg-surface text-ink focus:outline-none focus:ring-2 focus:ring-primary">
                    <p x-show="regErrors.full_name" class="mt-1 text-xs text-danger" x-text="(regErrors.full_name || [])[0]"></p>
                </div>
                <div>
                    <select x-model="reg.id_type"
                            class="w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg focus:bg-surface text-ink focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="MyKad">MyKad</option>
                        <option value="Passport">Passport</option>
                        <option value="Others">Other ID</option>
                    </select>
                    <p x-show="regErrors.id_type" class="mt-1 text-xs text-danger" x-text="(regErrors.id_type || [])[0]"></p>
                </div>
                <div>
                    <input type="text" x-model="reg.id_number" placeholder="ID number *"
                           class="w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg focus:bg-surface text-ink focus:outline-none focus:ring-2 focus:ring-primary">
                    <p x-show="regErrors.id_number" class="mt-1 text-xs text-danger" x-text="(regErrors.id_number || [])[0]"></p>
                </div>
                <div>
                    <input type="date" x-model="reg.date_of_birth" title="Date of birth"
                           class="w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg focus:bg-surface text-ink focus:outline-none focus:ring-2 focus:ring-primary">
                    <p x-show="regErrors.date_of_birth" class="mt-1 text-xs text-danger" x-text="(regErrors.date_of_birth || [])[0]"></p>
                </div>
                <div>
                    <input type="text" x-model="reg.nationality" placeholder="Nationality *"
                           class="w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg focus:bg-surface text-ink focus:outline-none focus:ring-2 focus:ring-primary">
                    <p x-show="regErrors.nationality" class="mt-1 text-xs text-danger" x-text="(regErrors.nationality || [])[0]"></p>
                </div>
                <div>
                    <input type="text" x-model="reg.phone" placeholder="Phone (optional)"
                           class="w-full px-3 py-2 text-sm bg-canvas-subtle border border-border rounded-lg focus:bg-surface text-ink focus:outline-none focus:ring-2 focus:ring-primary">
                    <p x-show="regErrors.phone" class="mt-1 text-xs text-danger" x-text="(regErrors.phone || [])[0]"></p>
                </div>
            </div>
            <p x-show="regErrors._general" class="mt-2 text-xs text-danger" x-text="(regErrors._general || [])[0]"></p>
            <div class="mt-3 flex gap-2">
                <button type="button" @click="register()" :disabled="regLoading"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg bg-primary text-on-primary hover:bg-primary-hover disabled:opacity-50">
                    <span x-show="!regLoading">Register &amp; select</span>
                    <span x-show="regLoading" x-cloak>Registering…</span>
                </button>
                <button type="button" @click="registering = false"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg border border-border text-ink hover:bg-canvas-subtle">
                    Cancel
                </button>
            </div>
        </div>
    </template>

    <template x-if="banner">
        <p class="mt-1 text-xs font-medium" :class="banner.danger ? 'text-danger' : 'text-warning'" x-text="banner.text"></p>
    </template>

    @if($hasError)
        <p id="{{ $name }}-error" class="mt-1 text-xs text-danger">{{ $errors->first($name) }}</p>
    @endif
</div>
