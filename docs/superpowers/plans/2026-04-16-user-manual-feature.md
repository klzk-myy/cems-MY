# User Manual Feature Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement complete user manual feature for new CEMS-MY users with zero system knowledge

**Architecture:** Standard Laravel controller -> view pattern, no authentication required, accessible to all authenticated users, added under System sidebar section

**Tech Stack:** Laravel 10.x, Blade templates, Tailwind CSS, existing layout structure

---

## Task 1: Create UserManualController

**Files:**
- Create: `app/Http/Controllers/UserManualController.php`

- [ ] **Step 1: Create controller class**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class UserManualController extends Controller
{
    public function index(): View
    {
        return view('user-manual.index');
    }
}
```

- [ ] **Step 2: Verify file is created**

---

## Task 2: Add Web Route

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add controller import**
  Add this line at top of file with other use statements:
  ```php
  use App\Http\Controllers\UserManualController;
  ```

- [ ] **Step 2: Add route inside authenticated group**
  Add this line in System routes section (after Audit routes, before closing }):
  ```php
  // User Manual
  Route::get('/user-manual', [UserManualController::class, 'index'])->name('user-manual.index');
  ```

---

## Task 3: Add Sidebar Navigation Link

**Files:**
- Modify: `resources/views/layouts/base.blade.php`

- [ ] **Step 1: Insert link in System section**
  Add this after Audit Log link (line 350) inside System nav-section:
  ```html
  <a href="/user-manual" class="nav-item {{ request()->is('user-manual*') ? 'active' : '' }}">
      <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
      </svg>
      <span>User Manual</span>
  </a>
  ```

---

## Task 4: Create User Manual View

**Files:**
- Create: `resources/views/user-manual/index.blade.php`
- Create directory first: `mkdir resources/views/user-manual`

- [ ] **Step 1: Create blade view file**

```blade
@extends('layouts.base')

@section('title', 'User Manual')

@section('content')

<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">CEMS-MY User Manual</h1>
        <p class="text-lg text-gray-600">Complete guide for new users</p>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="table-of-contents p-6 bg-gray-50 border-b">
            <h2 class="text-xl font-semibold mb-4">Contents</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <a href="#login" class="flex items-center gap-2 text-gray-700 hover:text-blue-600">
                    <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center text-blue-600 text-sm">1</span>
                    Logging In & First Steps
                </a>
                <a href="#dashboard" class="flex items-center gap-2 text-gray-700 hover:text-blue-600">
                    <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center text-blue-600 text-sm">2</span>
                    Understanding the Dashboard
                </a>
                <a href="#counters" class="flex items-center gap-2 text-gray-700 hover:text-blue-600">
                    <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center text-blue-600 text-sm">3</span>
                    Opening & Closing Counters
                </a>
                <a href="#transactions" class="flex items-center gap-2 text-gray-700 hover:text-blue-600">
                    <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center text-blue-600 text-sm">4</span>
                    Creating Transactions
                </a>
                <a href="#customers" class="flex items-center gap-2 text-gray-700 hover:text-blue-600">
                    <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center text-blue-600 text-sm">5</span>
                    Working with Customers
                </a>
                <a href="#reports" class="flex items-center gap-2 text-gray-700 hover:text-blue-600">
                    <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center text-blue-600 text-sm">6</span>
                    Running Reports
                </a>
                <a href="#compliance" class="flex items-center gap-2 text-gray-700 hover:text-blue-600">
                    <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center text-blue-600 text-sm">7</span>
                    Compliance Requirements
                </a>
                <a href="#troubleshooting" class="flex items-center gap-2 text-gray-700 hover:text-blue-600">
                    <span class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center text-blue-600 text-sm">8</span>
                    Troubleshooting
                </a>
            </div>
        </div>

        <div class="p-8 space-y-12">
            <section id="login" class="scroll-mt-16">
                <h2 class="text-2xl font-bold mb-4 border-b pb-3">1. Logging In & First Steps</h2>
                <div class="space-y-4">
                    <div class="tip-box bg-blue-50 p-4 rounded-lg border-l-4 border-blue-500">
                        <strong>💡 First time login:</strong> Your manager will provide you with a temporary password. You will be asked to change it on your first login.
                    </div>
                    <ol class="space-y-2 list-decimal pl-6">
                        <li>Enter your username and temporary password</li>
                        <li>Set a new secure password (minimum 12 characters)</li>
                        <li>Set up Multi-Factor Authentication (MFA) using your phone</li>
                        <li>Verify your MFA code</li>
                    </ol>
                </div>
            </section>

            <section id="dashboard" class="scroll-mt-16">
                <h2 class="text-2xl font-bold mb-4 border-b pb-3">2. Understanding the Dashboard</h2>
                <p>The dashboard shows your daily summary: today's transactions, counter status, alerts, and pending tasks. Everything you need for your shift is on this page.</p>
            </section>

            <section id="counters" class="scroll-mt-16">
                <h2 class="text-2xl font-bold mb-4 border-b pb-3">3. Opening & Closing Counters</h2>
                <div class="warning-box bg-yellow-50 p-4 rounded-lg border-l-4 border-yellow-500">
                    <strong>⚠️ Important:</strong> You MUST open your counter before processing any transactions.
                </div>
                <ol class="space-y-2 list-decimal pl-6">
                    <li>Go to Counters from the sidebar</li>
                    <li>Select your assigned counter</li>
                    <li>Click "Open Counter"</li>
                    <li>Count and enter opening float amounts for each currency</li>
                    <li>Confirm and open</li>
                </ol>
            </section>

            <section id="transactions" class="scroll-mt-16">
                <h2 class="text-2xl font-bold mb-4 border-b pb-3">4. Creating Transactions</h2>
                <h3 class="text-lg font-semibold mt-4 mb-2">Buying Foreign Currency</h3>
                <ol class="space-y-2 list-decimal pl-6">
                    <li>Click "New Transaction" on dashboard or go to Transactions page</li>
                    <li>Select "Buy" transaction type</li>
                    <li>Select the foreign currency</li>
                    <li>Enter amount (either MYR or foreign currency)</li>
                    <li>System will calculate the other amount automatically</li>
                    <li>Enter customer details</li>
                    <li>Verify amounts, then click "Process Transaction"</li>
                </ol>
            </section>

            <section id="customers" class="scroll-mt-16">
                <h2 class="text-2xl font-bold mb-4 border-b pb-3">5. Working with Customers</h2>
                <p>All transactions require customer identification for amounts over RM 3,000. For regular customers you can save their details for faster processing.</p>
            </section>

            <section id="reports" class="scroll-mt-16">
                <h2 class="text-2xl font-bold mb-4 border-b pb-3">6. Running Reports</h2>
                <p>Reports are available under the Reports section. Tellers have access to daily transaction reports and their counter history.</p>
            </section>

            <section id="compliance" class="scroll-mt-16">
                <h2 class="text-2xl font-bold mb-4 border-b pb-3">7. Compliance Requirements</h2>
                <div class="danger-box bg-red-50 p-4 rounded-lg border-l-4 border-red-500">
                    <strong>⚠️ Bank Negara Requirements:</strong> This system is audited. All actions are logged and cannot be deleted.
                </div>
                <ul class="space-y-2 list-disc pl-6">
                    <li>Transactions over RM 50,000 require Manager approval</li>
                    <li>All cash transactions over RM 10,000 are reported to CTOS automatically</li>
                    <li>Customer identification is required for all transactions over RM 3,000</li>
                </ul>
            </section>

            <section id="troubleshooting" class="scroll-mt-16">
                <h2 class="text-2xl font-bold mb-4 border-b pb-3">8. Troubleshooting</h2>
                <p>If you encounter problems:</p>
                <ol class="space-y-2 list-decimal pl-6">
                    <li>Check that your counter is open</li>
                    <li>Verify you have sufficient stock for the currency</li>
                    <li>Contact your manager for approval issues</li>
                    <li>Contact system administrator for technical issues</li>
                </ol>
            </section>
        </div>
    </div>
</div>

<style>
.scroll-mt-16 {
    scroll-margin-top: 4rem;
}
</style>

@endsection
