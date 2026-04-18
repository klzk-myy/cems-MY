# UI Bug Fix: Sidebar Orphaned Link

**Date:** 2026-04-17
**Status:** Approved
**Type:** Critical Bug Fix

## Issue

`resources/views/layouts/base.blade.php` has orphaned HTML at lines 365-369:
- Missing opening `<a href="...">` tag
- Has SVG icon and "User Manual" text but no link wrapper
- Has orphaned `</a>` closing tag

This creates malformed HTML and the "User Manual" link has no destination.

## Fix

Remove the orphaned User Manual nav item entirely since:
- No User Manual route exists
- No User Manual controller/method exists
- No User Manual view exists
- User Manual is not implemented in the system

## Code to Remove

```blade
                    <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <span>User Manual</span>
                </a>
```

## Impact

- Low risk - removes broken HTML
- No functionality lost (User Manual never existed)
- Improves sidebar HTML validity
