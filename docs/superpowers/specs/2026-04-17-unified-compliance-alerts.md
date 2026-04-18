# Unified Compliance Alerts Dashboard

## Context

Currently CEMS-MY has two separate compliance alert systems:
1. **Alert Triage** (`/compliance/alerts`) - Transaction-based alerts from `Alert` model
2. **Compliance Findings** (`/compliance/findings`) - Monitor-detected findings from `ComplianceFinding` model via API

Both serve compliance officers but require switching between two views. This spec defines a unified dashboard merging both into a single table with comprehensive filtering.

## Data Models

### Alert (Direct DB)
- **Model:** `App\Models\Alert`
- **Source:** Transaction flagging (velocity, structuring, sanctions hit, etc.)
- **Key fields:**
  - `priority` - `AlertPriority` enum (Critical, High, Medium, Low)
  - `type` - `ComplianceFlagType` enum
  - `status` - `FlagStatus` enum (Pending, InProgress, Resolved, Escalated, Rejected)
  - `customer_id` - FK to Customer
  - `assigned_to` - FK to User (compliance officer)
  - `reason` - description text
  - `created_at` - alert creation date

### ComplianceFinding (API)
- **Model:** `App\Models\Compliance\ComplianceFinding`
- **Source:** Automated monitors (velocity exceeded, structuring pattern, STR deadline, etc.)
- **Key fields:**
  - `severity` - `FindingSeverity` enum (Critical, High, Medium, Low)
  - `finding_type` - `FindingType` enum
  - `status` - `FindingStatus` enum (New, Reviewed, Dismissed, CaseCreated)
  - `subject_type` / `subject_id` - polymorphic (Customer or Transaction)
  - `details` - JSON with summary, description
  - `generated_at` - finding generation date

## Unified Schema

Both sources normalize into a common structure for display:

| Field | Alert | Finding | Notes |
|-------|-------|---------|-------|
| `id` | Alert.id | Finding.id | Prefixed: "A-{id}" for alerts, "F-{id}" for findings |
| `source` | "Alert" | "Finding" | Determines which detail page to link to |
| `priority` | Alert.priority | Finding.severity | Mapped to shared priority labels |
| `type` | Alert.type label | Finding.finding_type label | Display-friendly labels |
| `status` | Alert.status label | Finding.status label | Unified status grouping |
| `customer` | Alert.customer (loaded) | Finding.subject if Customer | Display name + IC |
| `assigned_to` | Alert.assignedTo.name | null | Findings have no assignee |
| `description` | Alert.reason | Finding.details['summary'] | Truncated to 100 chars |
| `date` | Alert.created_at | Finding.generated_at | For date filtering |
| `url` | `/compliance/alerts/{id}` | `/compliance/findings/{id}` | Detail page link |

## UI Design

### Route
- `GET /compliance/unified` - Unified alerts index
- Route name: `compliance.unified.index`

### Controller
- `App\Http\Controllers\Compliance\UnifiedAlertController`
- Fetches both Alert and ComplianceFinding data
- Normalizes into unified array for view
- Supports all filters via query string

### View
- `resources/views/compliance/unified/index.blade.php`
- Extends `layouts.base`
- Tailwind CSS styling consistent with app

### Stats Bar (Top)
Four summary cards showing combined totals:
1. **Total** - all items count
2. **Critical** - count where priority = Critical
3. **Pending/Open** - count where status needs attention
4. **Resolved Today** - count resolved/dismissed in last 24h

### Filters (Below stats)
Form with GET submission, horizontal layout:

| Filter | Type | Source | Notes |
|--------|------|--------|-------|
| Source | Select | All/Alert/Findings | Default: All |
| Priority | Select | Critical/High/Medium/Low | Default: All |
| Status | Select | Open, In Review, Resolved, Dismissed | Groups statuses |
| Type | Select | Combined list of all alert + finding types | Default: All |
| Customer | Text input | Search by customer name | Partial match |
| From Date | Date picker | Filter by date | Default: null |
| To Date | Date picker | Filter by date | Default: null |

**Filter actions:**
- Apply Filters button
- Clear Filters link (returns to `/compliance/unified`)

### Unified Table

Columns:
1. **Source** - badge (Alert=blue, Finding=purple)
2. **Priority** - color-coded badge (Critical=red, High=orange, Medium=yellow, Low=green)
3. **Type** - text description
4. **Customer** - name with link to customer profile
5. **Status** - badge (Open=blue, InReview=yellow, Resolved=green, Dismissed=gray)
6. **Assigned To** - officer name or "Unassigned" badge
7. **Date** - formatted date
8. **Actions** - View button (links to appropriate detail page)

### Pagination
- 25 items per page
- Standard Laravel pagination
- Preserves filter params via `withQueryString()`

## Filtering Logic

### Status Mapping (Unified View)
| Unified Status | Alert Statuses | Finding Statuses |
|----------------|---------------|------------------|
| Open | Pending | New |
| In Review | InProgress, Escalated | Reviewed |
| Resolved | Resolved | CaseCreated |
| Dismissed | Rejected | Dismissed |

### Priority Mapping
| Unified Priority | Alert Priorities | Finding Severities |
|------------------|------------------|--------------------|
| Critical | Critical | Critical |
| High | High | High |
| Medium | Medium | Medium |
| Low | Low | Low |

### Type Options (Combined)
From Alert (`ComplianceFlagType`):
- LargeAmount
- SanctionsHit
- Velocity
- Structuring
- EddRequired
- PepStatus
- SanctionMatch
- HighRiskCustomer
- UnusualPattern
- ManualReview
- HighRiskCountry
- RoundAmount
- ProfileDeviation
- CounterfeitCurrency
- RiskScoreEscalation
- AmlRuleTriggered

From Finding (`FindingType`):
- VelocityExceeded
- StructuringPattern
- SanctionMatch
- StrDeadline
- CounterfeitAlert
- LocationAnomaly
- CurrencyFlowAnomaly
- RiskScoreChange

### Customer Search
- Searches `customers.full_name` for matches
- Applied as LIKE %term% on both sources
- For Findings, joins through polymorphic subject

## Implementation Notes

### Controller Strategy
Since Findings come from an API call and Alerts from direct DB, the controller will:
1. Build Alert query with filters
2. Make API call for Findings with similar filters
3. Merge and sort by date descending
4. Return unified collection

### Performance Consideration
For large datasets, consider:
- Caching Findings API response (5 min)
- Lazy loading customer relationships
- Database-level pagination for Alerts

### Access Control
- Route should require `role:compliance` or `role:admin`
- Consistent with existing alert/finding routes

## Files to Create/Modify

### New Files
- `app/Http/Controllers/Compliance/UnifiedAlertController.php`
- `resources/views/compliance/unified/index.blade.php`

### Modified Files
- `routes/web.php` - Add route for `/compliance/unified`
- `resources/views/layouts/base.blade.php` - Add sidebar link

## Testing
- Unit test for normalized data mapping
- Feature test for filter combinations
- Test that both alerts and findings appear in unified view
