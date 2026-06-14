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

{{-- Warning Alert --}}
<x-alert type="warning">
    This action cannot be undone.
</x-alert>

{{-- Info Alert --}}
<x-alert type="info" dismissible>
    New feature available! Check out the latest updates.
</x-alert>
```

**Props:**
- `type`: `success` | `error` | `warning` | `info` (default: `info`)
- `title`: Optional alert title
- `dismissible`: Boolean, shows close button (default: `false`)

---

### 2. Button Component

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
- `primary`: Black background (default)
- `secondary`: White with border
- `danger`: Red background
- `success`: Green background
- `warning`: Yellow background
- `info`: Blue background
- `indigo`: Indigo background
- `purple`: Purple background
- `teal`: Teal background
- `ghost`: Transparent background

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

### 3. Input Component

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
- Error state styling (red border)
- Error message display
- Focus ring styling
- Disabled state styling

---

### 4. Select Component

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

---

### 5. Badge Component

**File:** `resources/views/components/badge.blade.php`

**Usage:**
```blade
{{-- Status Badges --}}
<x-badge variant="success">Completed</x-badge>
<x-badge variant="danger">Cancelled</x-badge>
<x-badge variant="warning">Pending</x-badge>
<x-badge variant="info">New</x-badge>
<x-badge variant="gray">Draft</x-badge>

{{-- With Icon --}}
<x-badge variant="success" icon="✓">Verified</x-badge>
```

**Variants:**
- `success`: Green (completed, approved)
- `danger`: Red (error, cancelled)
- `warning`: Yellow (pending, warning)
- `info`: Blue (info, new)
- `gray`: Gray (neutral, draft)

**Props:**
- `variant`: Badge color variant
- `size`: `sm` | `md` (default: `md`)
- `icon`: Optional icon text or SVG

---

### 6. Card Component

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

---

### 7. Card Section Component

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

---

### 8. Table Component

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

### 9. Empty State Component

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

{{-- Standalone --}}
<div class="text-center py-12">
    <x-empty-state message="No transactions found" />
</div>
```

**Props:**
- `message`: Message to display (default: "No results found")
- `colspan`: Table column span (default: 1)
- `icon`: Optional custom SVG path

---

### 10. Stat Card Component

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

### 11. Stat Grid Component

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

### 13. Filter Bar Component

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

### 14. Progress Bar Component

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
- `max`: Maximum value (default: 100)
- `label`: Optional label
- `color`: `blue` | `green` | `red` | `yellow` (default: `blue`)
- `size`: `sm` | `md` | `lg` (default: `md`)

---

### 15. Chart Bar Component

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

### 16. Navigation Component

**File:** `resources/views/components/navigation.blade.php`

**Usage:** (See existing implementation)

---

### 17. App Layout Component

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
| Primary | `bg-primary`, `text-primary` | Primary buttons, focus rings |
| Primary Hover | `bg-primary-hover` | Primary button hover |
| Success | `bg-success`, `bg-success-subtle`, `text-success-text` | Success states |
| Danger | `bg-danger`, `bg-danger-subtle`, `text-danger-text` | Error / danger states |
| Warning | `bg-warning`, `bg-warning-subtle`, `text-warning-text` | Warning states |
| Info | `bg-info`, `bg-info-subtle`, `text-info-text` | Info states |

**Avoid:** `bg-[#0a0a0a]`, `border-[#e5e5e5]`, `bg-white`, `text-gray-900`, `text-gray-500`.

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

### Spacing

**Common Patterns:**
- Card padding: `p-6` (1.5rem / 24px)
- Form field spacing: `mb-4` (1rem / 16px)
- Gap between elements: `gap-4` (1rem / 16px)
- Page padding: `p-6` (1.5rem / 24px)

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
3. **Keep forms consistent** - use Input/Select components
4. **Show loading states** - use `loading` prop on buttons
5. **Provide context** - use Alert components for feedback
6. **Empty states matter** - use EmptyState component
7. **Responsive by default** - components work on all screen sizes
8. **Accessible** - components include proper ARIA attributes

---

## Component Checklist

Before deploying a view, ensure:

- [ ] All buttons use `<x-button>`
- [ ] All inputs use `<x-input>`
- [ ] All selects use `<x-select>`
- [ ] All badges use `<x-badge>`
- [ ] All alerts use `<x-alert>`
- [ ] All cards use `<x-card>` or `<x-card-section>`
- [ ] All tables use `<x-table>` or `<x-data-table>`
- [ ] Empty states use `<x-empty-state>`
- [ ] Page headers use `<x-page-header>`
- [ ] Stats use `<x-stat-card>` and `<x-stat-grid>`
- [ ] Filters use `<x-filter-bar>`
- [ ] No arbitrary Tailwind values like `bg-[#0a0a0a]` or `border-[#e5e5e5]`
- [ ] No raw Tailwind grays like `bg-white`, `text-gray-900`, or `text-gray-500`
- [ ] Theme tokens are used for backgrounds, borders, and text
- [ ] Dark mode works by using theme tokens (no `dark:bg-*-dark` pairs needed)

---

## Need Help?

See existing components in `resources/views/components/` for examples.

Run code formatter after changes:
```bash
vendor/bin/pint --dirty --format agent
```