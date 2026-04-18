# Phase 1: Customer Screening UI

## Overview
Add web UI for viewing customer screening status and history.

## Pages Required

### 1. Customer Screening History (`/compliance/screening/{customerId}`)
- **Route:** `GET /compliance/screening/{customerId}`
- **Controller:** `ScreeningController` (new, web controller)
- **View:** `compliance/screening/show.blade.php`

**Features:**
- Current screening status card (last screened, result, score)
- Screening history table (date, score, action, matches)
- "Re-screen Customer" button
- Match details expandable

## Data from API

**GET `/api/v1/screening/customer/{id}/status` returns:**
- `sanction_hit` (bool)
- `last_screened_at` (datetime)
- `last_result` (clear/flag/block)
- `last_match_score` (float)

**GET `/api/v1/screening/customer/{id}/history` returns:**
- Array of screening results with matches

**POST `/api/v1/screening/customer/{id}` triggers new screening**

## Integration

Add to existing customer detail page or create dedicated section.

## Acceptance Criteria

1. View screening history for any customer
2. See current screening status at a glance
3. Trigger manual re-screen with confirmation
4. View match details when applicable
