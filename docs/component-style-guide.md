# Blade Component Style Guide

## Overview

This application uses a comprehensive Blade component library built on Tailwind CSS v4. All views should use these components for consistent styling.

---

## Component Library

### 1. Alert Component

**File:** `resources/views/components/alert.blade.php`

**Usage:**
```blade
{{-- Success Alert --}}
<x-alert type="success" title="Success!">
    Your changes have been saved successfully.
</x-alert>

{{-- Error Alert --}}
<x-alert type="error" title="Error">
    Something went wrong. Please try again.
</x-alert>

{{-- Danger Alert (alias for error) --}}
<x-alert type="danger" title="Danger">
    This action is destructive.
</x-alert>

{{-- Warning Alert --}}
<x-alert type="warning">
    This action cannot be undone.
</x-alert>

{{-- Info Alert --}}
<x-alert type="info" dismissible>
    New feature available! Check out the latest updates.
</x-alert>

{{-- Without Icon --}}
<x-alert type="info" :icon="false">
    This alert has no icon.
</x-alert>
```

**Props:**
- `type`: `success` | `error` | `danger` | `warning` | `info` (default: `info`)
  - `danger` is an alias for `error`
- `title`: Optional alert title
- `dismissible`: Boolean, shows close button (default: `false`)
- `icon`: Boolean, show/hide alert icon (default: `true`)

**Styling:** Uses theme tokens only (no raw colors). Backgrounds from `*-subtle` tokens, borders from `*-border` tokens, text from `*-text` tokens.

---

### 2. Badge Component

**File:** `resources/views/components/badge.blade.php`

**Usage:**
```blade
{{-- Status Badges --}}
<x-badge variant="success">Completed</x-badge>
<x-badge variant="danger">Cancelled</x-badge>
<x-badge variant="warning">Pending</x-badge>
<x-badge variant="info">New</x-badge>
<x-badge variant="gray">Draft</x-badge>
<x-badge variant="purple">Premium</x-badge>

{{-- With Icon --}}
<x-badge variant="success" icon="✓">Verified</x-badge>

{{-- Sizes --}}
<x-badge variant="info" size="sm">Small</x-badge>
<x-badge variant="info" size="lg">Large</x-badge>
```

**Variants:**
- `success`: `bg-success-subtle text-success-text`
- `danger`: `bg-danger-subtle text-danger-text`
- `warning`: `bg-warning-subtle text-warning-text`
- `info`: `bg-info-subtle text-info-text`
- `gray`: `bg-canvas-subtle text-ink-muted` (neutral/draft)
- `purple`: `bg-accent/10 text-accent` (premium/special)

**Props:**
- `variant`: Badge color variant
- `size`: `sm` | `md` (default: `md`) | `lg`
- `icon`: Optional icon text or SVG

---

### 3. Button Component

**File:** `resources/views/components/button.blade.php`

**Usage:**
```blade
{{-- Primary Button --}}
<x-button variant="primary">Save Changes</x-button>

{{-- Secondary Button --}}
<x-button variant="secondary">Cancel</x-button>

{{-- Danger Button --}}
<x-button variant="danger">Delete</x-button>

{{-- Link Button --}}
<x-button variant="primary" href="/dashboard">Go to Dashboard</x-button>

{{-- Loading State --}}
<x-button variant="primary" loading>Saving...</x-button>

{{-- With Icon --}}
<x-button variant="success" icon="M5 13l4 4L19 7">Approve</x-button>

{{-- Disabled State --}}
<x-button variant="primary" disabled>Submit</x-button>

{{-- Small Button --}}
<x-button variant="primary" size="sm">Small</x-button>

{{-- Large Button --}}
<x-button variant="primary" size="lg">Large</x-button>
```

**Variants:**
- `primary`: Uses `bg-primary`, `text-on-primary`, `hover:bg-primary-hover`
- `secondary`: Uses `bg-surface`, `border-border`, `text-ink-muted`, `hover:bg-canvas-subtle`
- `danger`: Uses `bg-danger`, `text-on-danger`, `hover:bg-danger-hover`
- `success`: Uses `bg-success`, `text-on-success`, `hover:bg-success-hover`
- `warning`: Uses `bg-warning`, `text-on-warning`, `hover:bg-warning-hover`
- `info`: Uses `bg-info`, `text-on-info`, `hover:bg-info-hover`
- `indigo`: Raw Tailwind color (`bg-indigo-600`, `hover:bg-indigo-700`)
- `purple`: Raw Tailwind color (`bg-purple-600`, `hover:bg-purple-700`)
- `teal`: Raw Tailwind color (`bg-teal-600`, `hover:bg-teal-700`)
- `ghost`: Uses `bg-transparent`, `text-ink-muted`, `hover:bg-canvas-subtle`

**Sizes:**
- `sm`: Small (px-3 py-1.5 text-xs)
- `md`: Medium (px-4 py-2 text-sm) - default
- `lg`: Large (px-6 py-3 text-base)

**Props:**
- `variant`: Button color variant
- `size`: Button size
- `href`: URL (makes it a link)
- `type`: Button type (button|submit|reset)
- `disabled`: Disabled state
- `loading`: Loading state with spinner
- `icon`: SVG path for icon

---

### 4. Card Component

**File:** `resources/views/components/card.blade.php`

**Usage:**
```blade
{{-- Basic Card --}}
<x-card>
    Card content here
</x-card>

{{-- Card with Title --}}
<x-card title="Card Title">
    Card content here
</x-card>

{{-- Card with Description --}}
<x-card title="Card Title" description="Card description">
    Card content here
</x-card>
```

**Props:**
- `title`: Card title
- `description`: Card description/subtitle

**Note:** The component automatically applies body padding (`p-6`). No manual padding wrappers are needed.

---

### 5. Card Section Component

**File:** `resources/views/components/card-section.blade.php`

**Usage:**
```blade
<x-card-section title="Transaction Details" :actions="true">
    <p>Card content here</p>
    
    <x-slot:actions>
        <x-button variant="secondary">Edit</x-button>
        <x-button variant="primary">Save</x-button>
    </x-slot:actions>
</x-card-section>
```

**Props:**
- `title`: Section title
- `actions`: Boolean, shows actions slot

**Note:** The component automatically applies body padding. No additional padding wrappers required.

---

### 6. Checkbox Component

**File:** `resources/views/components/checkbox.blade.php`

**Usage:**
```blade
{{-- Basic Checkbox --}}
<x-checkbox name="terms" label="I accept the terms and conditions" required />

{{-- With Help Text --}}
<x-checkbox name="newsletter" label="Subscribe to newsletter" help="Receive weekly updates" checked />

{{-- Inline (no margin) --}}
<div class="flex items-center gap-4">
    <x-checkbox name="option1" label="Option 1" inline />
    <x-checkbox name="option2" label="Option 2" inline />
</div>

{{-- Disabled State --}}
<x-checkbox name="disabled" label="Disabled option" disabled />
```

**Props:**
- `label`: Checkbox label text
- `name`: Input name (for validation)
- `value`: Checkbox value (default: `1`)
- `checked`: Boolean, pre-checked state (default: `false`)
- `required`: Required field
- `disabled`: Disabled state
- `help`: Help text below checkbox
- `inline`: Remove bottom margin

**Features:**
- Errors displayed with `text-danger-text` token
- Required asterisk uses `text-danger` token
- Automatic error state styling

---

### 7. DataTable Component

**File:** `resources/views/components/data-table.blade.php`

**Usage:**
```blade
<x-data-table :columns="$columns" :data="$users" :has-data="true">
    {{-- Custom row rendering --}}
    @foreach($users as $user)
        <tr>
            <td class="px-4 py-2">{{ $user->name }}</td>
            <td class="px-4 py-2">{{ $user->email }}</td>
            <td class="px-4 py-2">
                <x-button variant="secondary" size="sm">Edit</x-button>
            </td>
        </tr>
    @endforeach
</x-data-table>

{{-- With Pagination --}}
<x-data-table :columns="$columns" :data="$paginatedUsers">
    {{-- rows --}}
</x-data-table>

{{-- Hide Actions Column --}}
<x-data-table :columns="$columns" :data="$data" :has-actions="false">
    {{-- rows without actions column --}}
</x-data-table>

{{-- Custom Empty State --}}
<x-data-table :columns="$columns" :data="null" empty-message="No records match your search">
    {{-- will show empty state --}}
</x-data-table>
```

**Props:**
- `columns`: Array of column definitions (`['key' => 'name', 'label' => 'Name', 'sortable' => true]`)
- `data`: Collection or paginator of data rows
- `hasData`: Boolean indicating if data is present (alternative to checking `$data`)
- `columnCount`: Number of columns (for empty state colspan calculation)
- `hasActions`: Boolean to show/hide the actions column (default: `true`)
- `searchable`: Boolean to show/hide search input (default: `true`)
- `sortable`: Boolean to enable column sorting (default: `true`)
- `emptyMessage`: Custom empty state message (default: "No records found")

**Features:**
- Automatic search input
- Sortable column headers
- Pagination support for LengthAwarePaginator
- Empty state with auto colspan

---

### 8. Empty State Component

**File:** `resources/views/components/empty-state.blade.php`

**Usage:**
```blade
{{-- In Table --}}
<x-table>
    <x-slot:thead>...</x-slot:thead>
    <x-slot:tbody>
        <x-empty-state message="No results found" :colspan="3" />
    </x-slot:tbody>
</x-table>

{{-- Standalone (div) --}}
<div class="text-center py-12">
    <x-empty-state as="div" message="No transactions found" icon="search" />
</div>

{{-- Custom Icon --}}
<x-empty-state message="No data" icon="M12 4v16m8-8H4" />
```

**Props:**
- `message`: Message to display (default: "No results found")
- `colspan`: Table column span (default: `1`)
- `icon`: Optional custom SVG path
- `as`: Element type - `'tr'` (default, for table contexts) or `'div'` (for standalone)

**Polymorphic:** Use `as="div"` for non-table contexts, `as="tr"` for table rows.

---

### 9. Filter Bar Component

**File:** `resources/views/components/filter-bar.blade.php`

**Usage:**
```blade
<x-filter-bar>
    <x-input name="search" placeholder="Search..." inline />
    <x-select name="status" 
              :options="['active' => 'Active', 'inactive' => 'Inactive']" 
              inline />
    <x-button variant="primary" type="submit">Filter</x-button>
</x-filter-bar>
```

**Props:**
- `method`: Form method (default: GET)
- `class`: Additional CSS classes

**Note:** Use `inline` prop on child inputs to remove bottom margins.

---

### 10. Input Component

**File:** `resources/views/components/input.blade.php`

**Usage:**
```blade
{{-- Basic Input --}}
<x-input name="email" type="email" label="Email Address" required />

{{-- Input with Help Text --}}
<x-input name="password" 
         type="password" 
         label="Password"
         help="Must be at least 8 characters"
         required />

{{-- Disabled Input --}}
<x-input name="readonly_field" 
         value="Read only" 
         readonly />

{{-- Inline Input (no margin) --}}
<x-input name="search" 
         placeholder="Search..." 
         inline />
```

**Props:**
- `label`: Field label
- `name`: Input name (for validation)
- `type`: Input type (text|email|password|number|etc.)
- `required`: Required field
- `disabled`: Disabled state
- `readonly`: Read-only state
- `placeholder`: Placeholder text
- `help`: Help text below input
- `inline`: Remove bottom margin

**Automatically handles:**
- Error state styling (red border using `border-danger`)
- Error message display (using `text-danger-text` token)
- Focus ring styling
- Disabled state styling (using `disabled:bg-canvas-subtle`, `disabled:text-ink-muted`)
- Required asterisk (using `text-danger` token)

---

### 11. Navigation Component

**File:** `resources/views/components/navigation.blade.php`

**Usage:** (See existing implementation)

---

### 12. Page Header Component

**File:** `resources/views/components/page-header.blade.php`

**Usage:**
```blade
<x-page-header title="Users" :actions="true">
    Manage system users and their permissions.
    
    <x-slot:actions>
        <x-button variant="secondary">Import</x-button>
        <x-button variant="primary" icon="M12 4v16m8-8H4">Add User</x-button>
    </x-slot:actions>
</x-page-header>
```

**Props:**
- `title`: Page title
- `actions`: Boolean, shows actions slot

**Slots:**
- Default slot: Page description/subtitle
- `actions`: Right-aligned action buttons

---

### 13. Progress Bar Component

**File:** `resources/views/components/progress-bar.blade.php`

**Usage:**
```blade
<x-progress-bar :value="75" :max="100" label="Completion" />

{{-- With Color --}}
<x-progress-bar :value="75" color="green" />

{{-- Large Progress Bar --}}
<x-progress-bar :value="75" size="lg" />
```

**Props:**
- `value`: Current value
- `max`: Maximum value (default: `100`)
- `label`: Optional label
- `color`: `blue` | `green` | `red` | `yellow` (default: `blue`)
- `size`: `sm` | `md` | `lg` (default: `md`)

---

### 14. RadioGroup Component

**File:** `resources/views/components/radio-group.blade.php`

**Usage:**
```blade
{{-- Basic Radio Group --}}
<x-radio-group name="status" 
               :options="[
                   'active' => 'Active',
                   'inactive' => 'Inactive',
                   'pending' => 'Pending'
               ]"
               label="Account Status"
               required />

{{-- With Selected Binding --}}
<x-radio-group name="notification_frequency"
               :options="[
                   'daily' => 'Daily',
                   'weekly' => 'Weekly',
                   'monthly' => 'Monthly'
               ]"
               selected="weekly"
               label="Notification Frequency"
               help="How often would you like to receive notifications?" />

{{-- Inline Layout --}}
<div class="flex gap-6">
    <x-radio-group name="gender"
                   :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']"
                   inline />
</div>

{{-- Disabled --}}
<x-radio-group name="fixed_option"
               :options="['opt1' => 'Option 1']"
               disabled
               label="Fixed Choice" />
```

**Props:**
- `label`: Group label
- `name`: Radio name (all radios share same name)
- `options`: Array of `value => label` pairs
- `selected`: Selected value (default: `null`)
- `required`: Required field
- `disabled`: Disabled state for all radios
- `help`: Help text below the group
- `inline`: Remove bottom margin (radios flow inline)

**Features:**
- Error display with `text-danger-text` token
- Required asterisk uses `text-danger` token
- Uses `old()` helper for form repopulation
- Accessible labels and grouping

---

### 15. Select Component

**File:** `resources/views/components/select.blade.php`

**Usage:**
```blade
<x-select name="status"
          label="Status"
          :options="[
              'pending' => 'Pending',
              'approved' => 'Approved',
              'rejected' => 'Rejected'
          ]"
          required />
```

**Props:**
- `label`: Field label
- `name`: Select name (for validation)
- `options`: Array of value => label pairs
- `required`: Required field
- `disabled`: Disabled state
- `placeholder`: Placeholder text (default: "Select an option")
- `help`: Help text below select
- `inline`: Remove bottom margin

**Automatically handles:**
- Error border styling (`border-danger`)
- Error messages (using `text-danger-text` token)
- Required asterisk (using `text-danger` token)
- Focus ring and disabled states

---

### 16. Stat Card Component

**File:** `resources/views/components/stat-card.blade.php`

**Usage:**
```blade
<x-stat-card title="Total Revenue" 
             value="$45,231" 
             trend="+20.1%" 
             trend-type="positive">
    From last month
</x-stat-card>
```

**Props:**
- `title`: Stat title
- `value`: Main value
- `trend`: Trend value (e.g., "+20.1%")
- `trend-type`: `positive` | `negative` | `neutral`

---

### 17. Stat Grid Component

**File:** `resources/views/components/stat-grid.blade.php`

**Usage:**
```blade
<x-stat-grid :cols="4">
    <x-stat-card title="Revenue" value="$45,231" />
    <x-stat-card title="Users" value="2,345" />
    <x-stat-card title="Orders" value="1,234" />
    <x-stat-card title="Growth" value="+20.1%" />
</x-stat-grid>
```

**Props:**
- `cols`: Number of columns (1|2|3|4|6)

**Responsive Grid:**
- `cols=1`: 1 column on all screens
- `cols=2`: 1 column mobile, 2 columns tablet+
- `cols=3`: 1 column mobile, 2 columns tablet, 3 columns desktop
- `cols=4`: 1 column mobile, 2 columns tablet, 4 columns desktop (default)
- `cols=6`: 1 column mobile, 2 columns tablet, 3 columns desktop, 6 columns large

---

### 18. Table Component

**File:** `resources/views/components/table.blade.php`

**Usage:**
```blade
<x-table>
    <x-slot:thead>
        <tr>
            <th class="px-4 py-2">Name</th>
            <th class="px-4 py-2">Email</th>
            <th class="px-4 py-2">Actions</th>
        </tr>
    </x-slot:thead>
    <x-slot:tbody>
        <tr>
            <td class="px-4 py-2">John Doe</td>
            <td class="px-4 py-2">john@example.com</td>
            <td class="px-4 py-2">
                <x-button variant="secondary" size="sm">Edit</x-button>
            </td>
        </tr>
        <x-empty-state message="No users found" :colspan="3" />
    </x-slot:tbody>
</x-table>
```

**Slots:**
- `thead`: Table header
- `tbody`: Table body

---

### 19. Textarea Component

**File:** `resources/views/components/textarea.blade.php`

**Usage:**
```blade
{{-- Basic Textarea --}}
<x-textarea name="notes" label="Notes" rows="3" placeholder="Enter notes..." required>
    {{ old('notes') }}
</x-textarea>

{{-- With Help Text --}}
<x-textarea name="comments" 
            label="Comments" 
            help="Maximum 500 characters"
            :rows="5" />

{{-- Inline (no margin) --}}
<div class="flex gap-4">
    <x-textarea name="quick_note" label="Quick Note" :rows="2" inline />
</div>

{{-- Disabled/Readonly --}}
<x-textarea name="template" label="Template" disabled rows="4" />
```

**Props:**
- `label`: Field label
- `name`: Input name (for validation)
- `required`: Required field
- `placeholder`: Placeholder text
- `help`: Help text below textarea
- `rows`: Number of rows (default: `3`)
- `inline`: Remove bottom margin
- `disabled`: Disabled state
- `readonly`: Read-only state

**Automatically handles:**
- Error border (`border-danger`)
- Error messages (using `text-danger-text` token)
- Required asterisk (using `text-danger` token)
- Focus ring and disabled states

---

### 20. Chart Bar Component

**File:** `resources/views/components/chart-bar.blade.php`

**Usage:**
```blade
<x-chart-bar :value="75" :max="100" label="Revenue" />
```

**Props:**
- `value`: Bar value
- `max`: Maximum value
- `label`: Bar label
- `color`: Bar color variant

---

### 21. App Layout Component

**File:** `resources/views/components/app-layout.blade.php`

**Usage:**
```blade
<x-app-layout>
    <x-slot:title>Dashboard</x-slot:title>
    
    <x-page-header title="Dashboard">
        Welcome back!
    </x-page-header>
    
    <div class="space-y-6">
        {{-- Page content --}}
    </div>
</x-app-layout>
```

---

## Design System

### Colors

Use Tailwind theme tokens backed by CSS variables. Do **not** use raw hex values or `gray-*` colors directly.

| Token | Class | Usage |
|---|---|---|
| Canvas | `bg-canvas`, `bg-canvas-subtle` | Page / table-header backgrounds |
| Surface | `bg-surface` | Cards, tables, inputs, filters |
| Ink | `text-ink`, `text-ink-muted` | Body text, labels, muted text |
| Border | `border-border`, `divide-border` | Card/input borders, table dividers |
| Primary | `bg-primary`, `text-primary`, `focus:ring-primary/10` | Primary buttons, focus rings |
| Text on Primary | `text-on-primary` | Text/icons on primary buttons |
| Primary Hover | `bg-primary-hover` | Primary button hover |
| Success | `bg-success`, `bg-success-subtle`, `text-success-text` | Success states |
| Text on Success | `text-on-success` | Text/icons on success buttons |
| Success Hover | `bg-success-hover` | Success button hover |
| Danger | `bg-danger`, `bg-danger-subtle`, `text-danger-text` | Error / danger states |
| Text on Danger | `text-on-danger` | Text/icons on danger buttons |
| Danger Hover | `bg-danger-hover` | Danger button hover |
| Warning | `bg-warning`, `bg-warning-subtle`, `text-warning-text` | Warning states |
| Text on Warning | `text-on-warning` | Text/icons on warning buttons |
| Warning Hover | `bg-warning-hover` | Warning button hover |
| Info | `bg-info`, `bg-info-subtle`, `text-info-text` | Info states |
| Text on Info | `text-on-info` | Text/icons on info buttons |
| Info Hover | `bg-info-hover` | Info button hover |
| Accent | `bg-accent/10`, `text-accent` | Special highlights (e.g., purple badge) |
| Sidebar | `bg-sidebar`, `text-sidebar-text`, `border-sidebar-border` | Sidebar backgrounds |
| Sidebar Hover | `bg-sidebar-hover` | Sidebar item hover |
| Sidebar Ring | `focus:ring-sidebar-ring` | Sidebar focus state |

**Avoid:** `bg-[#0a0a0a]`, `border-[#e5e5e5]`, `bg-white`, `text-gray-900`, `text-gray-500`.

---

### Dark Mode

Dark mode is class-based. The `dark` class on `<html>` switches CSS custom-property values, so theme tokens automatically adapt.

```html
<html class="dark">
  <body class="bg-canvas-subtle text-ink">
    <!-- bg-canvas-subtle becomes #262626, text-ink becomes #f7f7f8 -->
  </body>
</html>
```

- Use theme token classes (`bg-surface`, `text-ink`, `border-border`) without `dark:*` utility pairs.
- Only use `dark:*` for values that are **not** theme tokens (e.g., `dark:hover:bg-white/10` on a dismiss button).
- Test with the nav toggle or by adding `class="dark"` to `<html>`.

---

### Typography

**Font Sizes:**
- xs: `0.75rem` (12px)
- sm: `0.875rem` (14px)
- base: `1rem` (16px)
- lg: `1.125rem` (18px)
- xl: `1.25rem` (20px)
- 2xl: `1.5rem` (24px)

**Font Weights:**
- medium: `font-medium` (500)
- semibold: `font-semibold` (600)
- bold: `font-bold` (700)

---

### Spacing

**Common Patterns:**
- Card padding: `p-6` (1.5rem / 24px)
- Form field spacing: `mb-4` (1rem / 16px)
- Gap between elements: `gap-4` (1rem / 16px)
- Page padding: `p-6` (1.5rem / 24px)

---

### Border Radius

- Buttons/Inputs: `rounded-lg` (0.5rem / 8px)
- Cards: `rounded-xl` (0.75rem / 12px)
- Badges: `rounded` (0.25rem / 4px)

---

## Migration Guide

### From Inline Styles to Components

**Before:**
```blade
<button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
    Save
</button>

<input class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">

<span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">
    Active
</span>

<div class="mb-4 p-4 text-sm text-red-700 bg-red-50 rounded-lg border border-red-200">
    Error message
</div>
```

**After:**
```blade
<x-button variant="primary">Save</x-button>

<x-input name="field_name" />

<x-badge variant="success">Active</x-badge>

<x-alert type="error">Error message</x-alert>
```

---

## Best Practices

1. **Always use components** instead of inline styles
2. **Use semantic variants** (success, danger, warning) instead of colors
3. **Keep forms consistent** - use Input/Select/Textarea/Checkbox/RadioGroup components
4. **Show loading states** - use `loading` prop on buttons
5. **Provide context** - use Alert components for feedback
6. **Empty states matter** - use EmptyState component (with `as="div"` for non-table contexts)
7. **Responsive by default** - components work on all screen sizes
8. **Accessible** - components include proper ARIA attributes
9. **Token-based styling** - use theme tokens, never raw colors
10. **Automatic padding** - Card and CardSection include padding automatically

---

## Component Checklist

Before deploying a view, ensure:

- [ ] All buttons use `<x-button>`
- [ ] All inputs use `<x-input>`
- [ ] All selects use `<x-select>`
- [ ] All textareas use `<x-textarea>`
- [ ] All checkboxes use `<x-checkbox>`
- [ ] All radio groups use `<x-radio-group>`
- [ ] All badges use `<x-badge>`
- [ ] All alerts use `<x-alert>`
- [ ] All cards use `<x-card>` or `<x-card-section>`
- [ ] All tables use `<x-table>` or `<x-data-table>`
- [ ] Empty states use `<x-empty-state>` (with appropriate `as` attribute)
- [ ] Page headers use `<x-page-header>`
- [ ] Stats use `<x-stat-card>` and `<x-stat-grid>`
- [ ] Filters use `<x-filter-bar>`
- [ ] Navigation uses `<x-navigation>`
- [ ] No arbitrary Tailwind values like `bg-[#0a0a0a]` or `border-[#e5e5e5]`
- [ ] No raw Tailwind grays like `bg-white`, `text-gray-900`, or `text-gray-500`
- [ ] Theme tokens are used for backgrounds, borders, and text
- [ ] Dark mode works by using theme tokens (no `dark:bg-*-dark` pairs needed)
- [ ] Error messages use `text-danger-text` token (handled automatically)
- [ ] Required asterisks use `text-danger` token (handled automatically)

---

## Need Help?

See existing components in `resources/views/components/` for examples.

Run code formatter after changes:
```bash
vendor/bin/pint --dirty --format agent
```
