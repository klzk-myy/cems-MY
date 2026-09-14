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
                        <span class="text-ink-muted" x-text="c.ic_number_masked ? ' · ' + c.ic_number_masked : ''"></span>
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
        </ul>
    </div>

    <template x-if="banner">
        <p class="mt-1 text-xs font-medium" :class="banner.danger ? 'text-danger' : 'text-warning'" x-text="banner.text"></p>
    </template>

    @if($hasError)
        <p id="{{ $name }}-error" class="mt-1 text-xs text-danger">{{ $errors->first($name) }}</p>
    @endif
</div>
