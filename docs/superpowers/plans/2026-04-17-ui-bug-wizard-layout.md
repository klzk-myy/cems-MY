# Plan: Fix Wizard Layout

## Steps

1. [ ] Edit `resources/views/transactions/wizard/index.blade.php` line 1
   - Change: `@extends('layouts.app')` → `@extends('layouts.base')`
2. [ ] Run lint check
3. [ ] Verify fix with git diff
