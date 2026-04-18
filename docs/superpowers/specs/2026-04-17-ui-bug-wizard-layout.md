# UI Bug Fix: Wizard Layout

**Date:** 2026-04-17
**Status:** Approved
**Type:** Critical Bug Fix

## Issue

`resources/views/transactions/wizard/index.blade.php` extends `layouts.app` which does not exist.

## Fix

**File:** `resources/views/transactions/wizard/index.blade.php`
**Line:** 1
**Change:** `@extends('layouts.app')` → `@extends('layouts.base')`

## Rationale

- `layouts.base` is the main application layout used by 131 other views
- `layouts.app` does not exist in `resources/views/layouts/`
- This fix aligns the wizard with the existing design system

## Impact

- Low risk - simple one-line change
- Affects transaction wizard UI only
