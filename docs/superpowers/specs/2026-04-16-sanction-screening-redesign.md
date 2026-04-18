# Sanction Screening System Redesign - Specification

**Date:** 2026-04-16
**Status:** Draft
**Version:** 1.0

---

## 1. Overview

Redesign the sanction screening system to:
1. Consolidate multiple screening services into a single `UnifiedSanctionScreeningService`
2. Add auto-import infrastructure for external sanction lists (UN, MOHA, OpenSanctions)
3. Fix inconsistent thresholds and broken return value handling
4. Persist all screening results for audit trail

---

## 2. Problem Statement

### 2.1 Current Issues

| Issue | Location | Impact |
|-------|----------|--------|
| Inconsistent thresholds | `SanctionScreeningService` (80%), `WatchlistApiService` (90%), `SanctionsRescreeningMonitor` (85%) | Unpredictable screening results |
| Broken return value | `CustomerRiskScoringService::rescreenCustomer()` checks `['is_match']` but service returns different structure | False positives/negatives |
| No auto-import | External lists must be manually uploaded | Outdated sanctions data |
| Not wired in transaction flow | `TransactionService` only calls `ComplianceService::checkSanctionMatch()` (substring only) | Misses fuzzy matches |
| No persistence | `screenCustomer()` results not saved to `screening_results` | No audit trail |
| DOB matching not used | `date_of_birth` field exists but never matched | False positives |

### 2.2 Current Screening Services

| Service | Method | Threshold | Issues |
|---------|--------|-----------|--------|
| `SanctionScreeningService` | `checkCustomer()` | 80% block, 60% flag | Works but not called during transactions |
| `SanctionScreeningService` | `screenName()` | 80% | Standalone name screening |
| `WatchlistApiService` | `screenCustomer()` | 90% block, 75% flag | Not wired, returns array (not ScreeningResult) |
| `ComplianceService` | `checkSanctionMatch()` | Substring only | Called in transaction flow, no scoring |

---

## 3. Data Model

### 3.1 Existing Tables

#### `sanction_lists`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | List name (e.g., "UN Consolidated", "MOHA Malaysia") |
| source_url | string | URL to download source |
| source_format | enum('xml','json','csv') | Format of source data |
| update_frequency | enum('daily','weekly','monthly') | How often to update |
| last_synced_at | timestamp | Last successful sync |
| status | enum('active','inactive') | List status |

#### `sanction_entries`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| list_id | foreignId | FK to sanction_lists |
| entity_name | string | Primary sanctioned name |
| entity_type | enum('Individual','Entity') | Type |
| aliases | text | Comma-separated alternative names |
| nationality | string(100) | Country of nationality |
| date_of_birth | date | Date of birth |
| reference_number | string | External reference (e.g., UN PRN) |
| listing_date | date | Date listed |
| details | json | Additional data |
| normalized_name | string | Lowercase, cleaned for matching |
| soundex_code | string(10) | Phonetic code |
| metaphone_code | string(20) | Phonetic code |
| status | enum('active','inactive') | Entry status |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `screening_results`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| customer_id | foreignId (nullable) | FK to customers |
| screening_type | enum('transaction','manual','batch','rescreen') | How screening was triggered |
| action | enum('clear','flag','block') | Result action |
| match_score | decimal(5,2) | Highest match score |
| matched_entries | json | Array of matched sanction entries |
| screened_at | timestamp | When screening occurred |
| screened_by | foreignId (nullable) | FK to users (for manual) |
| transaction_id | foreignId (nullable) | FK to transactions |
| notes | text | Optional notes |

### 3.2 New Tables

#### `sanction_import_logs`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| list_id | foreignId | FK to sanction_lists |
| imported_at | timestamp | Import timestamp |
| records_added | integer | New records added |
| records_updated | integer | Records updated |
| records_deactivated | integer | Records deactivated |
| status | enum('success','partial','failed') | Import status |
| error_message | text (nullable) | Error details if failed |
| triggered_by | enum('scheduled','manual') | How import was triggered |
| user_id | foreignId (nullable) | User who triggered (for manual) |

---

## 4. Source Configuration

### 4.1 Confirmed External Sources

| Source | URL | Format | Update Frequency |
|--------|-----|--------|------------------|
| **UN Consolidated** | `https://www.opensanctions.org/datasets/un_sc_sanctions/targets.nested.json` | JSON | Daily |
| **MOHA Malaysia** | `https://www.opensanctions.org/datasets/my_moha_sanctions/targets.nested.json` | JSON | Weekly |
| **OpenSanctions Full** | `https://www.opensanctions.org/datasets/sanctions/targets.nested.json` | JSON | Daily |

### 4.2 Source Configuration (config/sanctions.php)

```php
return [
    'sources' => [
        'un_consolidated' => [
            'name' => 'UN Security Council Consolidated',
            'url' => 'https://www.opensanctions.org/datasets/un_sc_sanctions/targets.nested.json',
            'format' => 'json',
            'frequency' => 'daily',
            'list_type' => 'international',
        ],
        'moha_malaysia' => [
            'name' => 'MOHA Malaysia Sanctions',
            'url' => 'https://www.opensanctions.org/datasets/my_moha_sanctions/targets.nested.json',
            'format' => 'json',
            'frequency' => 'weekly',
            'list_type' => 'national',
        ],
    ],
    'matching' => [
        'threshold_flag' => 75,    // Flag for review
        'threshold_block' => 90,   // Auto-block (not used - flag only policy)
        'algorithm' => 'levenshtein', // Primary algorithm
        'use_dob' => true,         // Require DOB match for high confidence
        'use_nationality' => true, // Require nationality match if available
    ],
    'import' => [
        'timeout' => 300,         // 5 minute timeout
        'retry_attempts' => 3,
        'retry_delay' => 60,       // 1 minute between retries
        'fallback_continue' => true, // Continue with available sources if one fails
    ],
];
```

---

## 5. UnifiedSanctionScreeningService

### 5.1 Class Structure

```php
namespace App\Services;

class UnifiedSanctionScreeningService
{
    public function __construct(
        protected MathService $math,
        protected AuditService $auditService,
    ) {}

    // Primary screening methods
    public function screenCustomer(Customer $customer): ScreeningResponse;
    public function screenName(string $name, ?string $dob = null, ?string $nationality = null): ScreeningResponse;
    public function screenTransaction(Transaction $transaction): ScreeningResponse;

    // Batch operations
    public function batchScreen(array $customerIds): BatchScreeningResponse;
    public function rescreenAll(): RescreenSummary;

    // History and status
    public function getHistory(Customer $customer): Collection<ScreeningResult>;
    public function getStatus(Customer $customer): ScreeningStatus;
}

class ScreeningResponse
{
    public function __construct(
        public readonly ScreeningAction $action,      // clear, flag, block
        public readonly float $confidenceScore,        // 0.00 - 100.00
        public readonly Collection $matches,            // Matched entries
        public readonly Carbon $screenedAt,
    ) {}

    public function isClear(): bool;
    public function isFlagged(): bool;
    public function isBlocked(): bool;
    public function toArray(): array;
}

class ScreeningMatch
{
    public function __construct(
        public readonly int $entryId,
        public readonly string $entityName,
        public readonly string $listName,
        public readonly string $listSource,
        public readonly float $matchScore,
        public readonly array $matchedFields,  // ['name', 'dob', 'nationality']
        public readonly ?Carbon $listingDate,
    ) {}
}
```

### 5.2 Matching Algorithm

```
1. Normalize input name:
   - Convert to lowercase
   - Remove punctuation, special characters
   - Collapse multiple spaces
   - Trim leading/trailing spaces

2. Query candidates from sanction_entries:
   - WHERE status = 'active'
   - AND list.status = 'active'
   - AND (normalized_name LIKE ? OR aliases LIKE ?)
   - Limit to 100 candidates for performance

3. Calculate match scores:
   a. Levenshtein similarity: similar_text() / max_length
   b. Token match: intersection of words / union of words
   c. Phonetic match: soundex/metaphone comparison

4. Apply DOB filter (if match_score >= 75%):
   - Require DOB match for confidence >= 90%

5. Return highest match score:
   - >= 75% → FLAG (compliance review required)
   - < 75% → CLEAR
```

### 5.3 Policy: Flag-Only (No Auto-Block)

Per decision: All matches are **flagged for compliance review**, no automatic blocking.

- `action = 'flag'` for any match >= 75%
- `action = 'clear'` for matches < 75%
- Block decisions are made by compliance officers after review

---

## 6. API Endpoints

### 6.1 Screening Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/v1/screening/customer/{id}` | On-demand customer screening | ComplianceOfficer+ |
| GET | `/api/v1/screening/customer/{id}/history` | Screening history | ComplianceOfficer+ |
| GET | `/api/v1/screening/customer/{id}/status` | Last/next screening info | ComplianceOfficer+ |
| POST | `/api/v1/screening/batch` | Bulk screening | ComplianceOfficer+ |

### 6.2 Sanctions List Management

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/v1/sanctions/lists` | List all configured sources | Admin |
| GET | `/api/v1/sanctions/entries` | Paginated entry listing | ComplianceOfficer+ |
| POST | `/api/v1/sanctions/import/trigger` | Manual import trigger | Admin |
| GET | `/api/v1/sanctions/import/logs` | Import history | Admin |
| POST | `/api/v1/sanctions/entries` | Manual entry add | Admin |
| PUT | `/api/v1/sanctions/entries/{id}` | Update entry | Admin |
| DELETE | `/api/v1/sanctions/entries/{id}` | Deactivate entry | Admin |

### 6.3 Request/Response Examples

#### POST `/api/v1/screening/customer/{id}`

**Request:**
```json
{
    "notes": "Annual re-screening"
}
```

**Response (200):**
```json
{
    "data": {
        "customer_id": 123,
        "action": "flag",
        "confidence_score": 82.5,
        "matches": [
            {
                "entry_id": 456,
                "entity_name": "John Smith",
                "list_name": "UN Consolidated",
                "match_score": 82.5,
                "matched_fields": ["name", "dob"],
                "listing_date": "2020-05-15"
            }
        ],
        "screened_at": "2026-04-16T10:30:00Z"
    }
}
```

#### GET `/api/v1/sanctions/entries`

**Query Parameters:**
- `page` (int): Page number
- `per_page` (int): Items per page (default 50, max 100)
- `list_id` (int): Filter by list
- `search` (string): Search entity name
- `status` (string): 'active', 'inactive', 'all'

**Response (200):**
```json
{
    "data": [
        {
            "id": 1,
            "entity_name": "ABU BAKR BASHIR",
            "entity_type": "Individual",
            "list": {
                "id": 2,
                "name": "MOHA Malaysia"
            },
            "nationality": "Indonesia",
            "date_of_birth": "1938-08-17",
            "reference_number": "MY-001",
            "status": "active",
            "listing_date": "2020-05-15"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 50,
        "total": 158
    }
}
```

---

## 7. Scheduled Jobs

### 7.1 Import Schedule

| Job | Schedule | Source |
|-----|----------|--------|
| `ImportSanctionsJob` | Daily 01:00 | UN Consolidated |
| `ImportSanctionsJob` | Sunday 02:00 | MOHA Malaysia |
| `ImportSanctionsJob` | Daily 03:00 | OpenSanctions Full |

### 7.2 Rescreening Schedule

| Job | Schedule | Scope |
|-----|----------|-------|
| `RescreenHighRiskCustomersJob` | Daily 04:00 | Risk score >= 70 |
| `RescreenAllCustomersJob` | Monthly 1st Sunday | All customers |

---

## 8. File Changes

### 8.1 New Files

| File | Purpose |
|------|---------|
| `app/Services/UnifiedSanctionScreeningService.php` | Consolidated screening service |
| `app/Http/Controllers/Api/V1/ScreeningController.php` | Screening API endpoints |
| `app/Http/Controllers/Api/V1/SanctionListController.php` | List management endpoints |
| `app/Jobs/ImportSanctionsJob.php` | Background import job |
| `app/Jobs/RescreenHighRiskCustomersJob.php` | Background rescreening job |
| `app/Console/Commands/SanctionsImportCommand.php` | Manual import command |
| `config/sanctions.php` | Source configuration |
| `database/migrations/2026_04_16_000001_create_sanction_import_logs_table.php` | Import log table |
| `database/migrations/2026_04_16_000002_add_list_source_to_sanction_entries.php` | Track entry source |
| `tests/Unit/UnifiedSanctionScreeningServiceTest.php` | Unit tests |
| `tests/Feature/ScreeningApiTest.php` | API integration tests |

### 8.2 Modified Files

| File | Change |
|------|--------|
| `app/Models/SanctionList.php` | New model |
| `app/Models/SanctionEntry.php` | Add source tracking |
| `app/Services/TransactionService.php` | Wire screening, persist results |
| `app/Console/Kernel.php` | Add scheduled import jobs |
| `routes/api.php` | Add screening routes |
| `database/seeders/SanctionListSeeder.php` | Default sources |

### 8.3 Deleted Files

| File | Reason |
|------|--------|
| `app/Services/SanctionScreeningService.php` | Replaced by UnifiedSanctionScreeningService |
| `app/Services/WatchlistApiService.php` | Replaced by UnifiedSanctionScreeningService |
| `app/Services/ComplianceService.php` | Screening methods moved to UnifiedSanctionScreeningService |
| `app/Jobs/ScreenCustomerJob.php` | Replaced by specific jobs |
| `app/Services/CustomerRiskScoringService.php` | Screening logic extracted |

---

## 9. Migration Plan

### Phase 1: Infrastructure
1. Create `sanction_lists` model and migration
2. Create `sanction_import_logs` migration
3. Add `source` column to `sanction_entries`
4. Create `config/sanctions.php`

### Phase 2: Import Service
1. Create `SanctionsImportService`
2. Create `ImportSanctionsJob`
3. Create `SanctionListSeeder` with default sources
4. Test manual import

### Phase 3: Unified Screening Service
1. Create `UnifiedSanctionScreeningService`
2. Implement matching algorithm with DOB/nationality
3. Create `ScreeningController`
4. Wire into `TransactionService`

### Phase 4: API Endpoints
1. Implement screening endpoints
2. Implement list management endpoints
3. Add proper authorization

### Phase 5: Scheduled Jobs
1. Configure Laravel scheduler
2. Add rescreening jobs
3. Add logging/monitoring

---

## 10. Acceptance Criteria

### Functional
- [ ] Customer can be screened via API
- [ ] Screening results persisted to `screening_results`
- [ ] DOB matching reduces false positives
- [ ] All external sources import successfully
- [ ] Manual entries can be added/edited
- [ ] Import logs track all changes

### Non-Functional
- [ ] Screening completes in < 500ms for single customer
- [ ] Batch import handles 10,000+ entries
- [ ] API authenticated via Laravel Sanctum
- [ ] All actions logged to audit trail

### Testing
- [ ] Unit tests for matching algorithm
- [ ] Unit tests for import service
- [ ] Integration tests for API endpoints
- [ ] Manual test: screen known sanctioned person

---

## 11. References

- OpenSanctions API: `https://www.opensanctions.org/docs/api/`
- UN Consolidated List: `https://main.un.org/securitycouncil/en/content/un-sc-consolidated-list`
- MOHA Malaysia: `https://www.moha.gov.my/utama/index`
