# Laravel Database Architecture & Eloquent Audit

**Project:** CEMS-MY (Currency Exchange Management System)
**Audit Date:** 2026-04-06
**Scope:** Complete audit of all 78 Eloquent models, 11 traits, and 4 base models
**Methodology:** Systematic file-by-file exploration with architectural analysis

---

## Executive Summary

The data layer demonstrates **excellent** architecture with:
- Proper use of BaseModel with defensive mass-assignment guard (`$guarded = ['*']`)
- Well-organized trait system for reusable relationships (BelongsToBranch, HasStatus, etc.)
- Modern enum casting (CddLevel, RiskRating, TransactionStatus, etc.)
- Proper relationship definitions with type hints
- Good use of local scopes

**Overall Database Architecture Score:** 9/10

---

## Model Findings (In-Scope)

### Fat Models: 0 Critical, 2 Minor

#### F1. Customer Model - Direct Service Dependency
* **Location:** `app/Models/Customer.php` (Line 11)
* **Category:** Fat Model
* **Description:** The Customer model imports `CustomerService` and `EncryptionService`. While these are only used in the class docblock, models should not have direct dependencies on services.
* **Refactor Recommendation:**
```php
// Remove service imports from model - move any service-related logic to observers or services
use App\Enums\CddLevel;
use App\Enums\IdType;
use App\Enums\RiskRating;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
// Remove: use App\Services\Customer\CustomerService;
// Remove: use App\Services\System\EncryptionService;
```

#### F2. User Model - MFA Service Dependency
* **Location:** `app/Models/User.php` (Line 10)
* **Category:** Fat Model
* **Description:** The User model imports `MfaService`. Models should not directly depend on services.
* **Refactor Recommendation:**
```php
// Remove service import from model
// Remove: use App\Services\System\MfaService;
```

---

### Missing Scopes: 0 Critical, 3 Minor

#### S1. Transaction Model - Common Status Filters
* **Location:** `app/Models/Transaction.php`
* **Category:** Missing Scope
* **Description:** The Transaction model uses `scopeCompleted()` and `scopeBuy()`/`scopeSell()` but could benefit from additional commonly used scopes.
* **Refactor Recommendation:**
```php
// Add to Transaction model:
public function scopePendingApproval(Builder $query): Builder
{
    return $query->where('status', TransactionStatus::PendingApproval);
}

public function scopeCompleted(Builder $query): Builder
{
    return $query->where('status', TransactionStatus::Completed);
}

public function scopeToday(Builder $query): Builder
{
    return $query->whereDate('created_at', today());
}
```

#### S2. FlaggedTransaction Model - Common Filters
* **Location:** `app/Models/FlaggedTransaction.php`
* **Category:** Missing Scope
* **Description:** The FlaggedTransaction model could benefit from scopes for common dashboard queries.
* **Refactor Recommendation:**
```php
// Add to FlaggedTransaction model:
public function scopeOpen(Builder $query): Builder
{
    return $query->where('status', FlagStatus::Open);
}

public function scopeHighPriority(Builder $query): Builder
{
    return $query->whereIn('flag_type', ['Sanction_Match', 'Structuring', 'Velocity'])
        ->where('status', '!=', FlagStatus::Resolved);
}
```

#### S3. Alert Model - Common Filters
* **Location:** `app/Models/Alert.php`
* **Category:** Missing Scope
* **Description:** The Alert model could benefit from scopes for dashboard queries.
* **Refactor Recommendation:**
```php
// Add to Alert model:
public function scopeCritical(Builder $query): Builder
{
    return $query->where('level', SystemAlertLevel::Critical);
}

public function scopeUnacknowledged(Builder $query): Builder
{
    return $query->whereNull('acknowledged_at');
}
```

---

### Relationship Optimization: 0 Critical, 2 Minor

#### R1. Customer Model - Missing $with for Frequently Accessed Relations
* **Location:** `app/Models/Customer.php`
* **Category:** Relationship Optimization
* **Description:** The Customer model's `transactions` relationship is frequently accessed. While `$with` can cause memory bloat, the `latestTransaction` relationship is already defined as `HasOne` which is efficient.
* **Current Code:**
```php
public function latestTransaction(): HasOne
{
    return $this->hasOne(Transaction::class)->latestOfMany();
}
```
* **Proposed Solution:** No change needed. The `latestTransaction` relationship is properly defined.

#### R2. Transaction Model - Computed Reference Accessor
* **Location:** `app/Models/Transaction.php` (Lines 135-138)
* **Category:** Relationship Optimization
* **Description:** The `reference` accessor uses string concatenation. This is fine but could be documented as a computed attribute.
* **Current Code:**
```php
public function getReferenceAttribute(): string
{
    return 'TX-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
}
```
* **Proposed Solution:** No change needed. The accessor is well-documented.

---

### Casting and Attributes: 0 Critical, 1 Minor

#### C1. Customer Model - risk_rating Casting Inconsistency
* **Location:** `app/Models/Customer.php` (Line 104)
* **Category:** Casting
* **Description:** The `risk_rating` is cast to `RiskRating` enum, but the model also handles string fallbacks in `isHigherRisk()` and `getRiskVariantAttribute()`. This suggests the casting may not be applied consistently across all records.
* **Current Code:**
```php
protected $casts = [
    'risk_rating' => RiskRating::class,
    // ...
];

public function isHigherRisk(): bool
{
    if ($this->risk_rating instanceof RiskRating) {
        return $this->risk_rating === RiskRating::Medium
            || $this->risk_rating === RiskRating::High;
    }
    // Handle string fallback
    return in_array($this->risk_rating, ['Medium', 'High']);
}
```
* **Proposed Solution:** The string fallback is defensive but should not be needed if casting is working. Consider adding a database migration to ensure all records use the enum value:
```php
// Keep casting, remove string fallback once data is cleaned up:
public function isHigherRisk(): bool
{
    return $this->risk_rating === RiskRating::Medium
        || $this->risk_rating === RiskRating::High;
}
```

---

## Out-of-Scope Findings

### Database Migration Issues

| Issue | File | Severity | Description |
|-------|------|----------|-------------|
| **M1** | `transactions` table | Low | Consider adding composite index on `(branch_id, created_at)` for dashboard queries |
| **M2** | `flagged_transactions` table | Low | Consider adding index on `(status, flag_type)` for dashboard filters |
| **M3** | `alerts` table | Low | Consider adding index on `(level, acknowledged_at)` for dashboard widgets |

### Controller Issues

| Issue | Controller | Severity | Description |
|-------|------------|----------|-------------|
| **CT1** | `DashboardController.php` | Low | Uses `remember()` for caching - consider adding cache invalidation on transaction creation |
| **CT2** | `CustomerController.php` | Low | Multiple `load*` calls after initial query - could be consolidated |

---

## Summary Statistics

| Metric | Count |
|--------|-------|
| **Total models audited** | 78 |
| **Total traits audited** | 11 |
| **Total base models audited** | 4 |
| **Fat model issues** | 2 |
| **Missing scopes** | 3 |
| **Relationship optimization** | 2 |
| **Casting issues** | 1 |
| **Out-of-scope findings** | 5 |

---

## Top 5 Recommendations

1. **Remove service imports from models** - Customer and User models should not import services
2. **Add common scopes to Transaction model** - `scopePendingApproval()`, `scopeToday()`
3. **Add common scopes to FlaggedTransaction model** - `scopeHighPriority()`
4. **Add common scopes to Alert model** - `scopeCritical()`, `scopeUnacknowledged()`
5. **Clean up risk_rating casting** - Remove string fallback once data is consistent

---

## Conclusion

The data layer is **excellent** with:
- Zero critical issues
- Proper use of BaseModel with defensive mass-assignment guard
- Well-organized trait system for reusable relationships
- Modern enum casting throughout
- Proper relationship definitions with type hints
- Good use of local scopes

The 2 fat model findings are minor and relate to service imports in docblocks rather than actual service usage. The missing scopes are opportunities for improved code reuse but do not affect functionality.

**Overall Database Architecture Score:** 9/10

---

*End of Database Architecture Audit*

---
## Post-Architecture-Fix Update (2026-08-30)
- Composite indexes added: transactions (`branch_id`, `created_at`), flagged_transactions (`status`, `flag_type`), alerts (`priority`, `status`).
- Scopes added to Transaction (`PendingApproval`, `Today`), FlaggedTransaction (`Open`, `HighPriority`), Alert (`Critical`, `Unacknowledged`).
- Architecture score remains 9/10; indexes improve query performance.
