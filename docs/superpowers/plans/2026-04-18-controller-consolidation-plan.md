# Controller Consolidation Plan

> **Goal:** Consolidate 6 Web+API controller pairs into unified controllers using content negotiation

## Overview

Each Web/API controller pair has significant duplicate logic. The plan is to:
1. Keep web routes pointing to web controller
2. Keep API routes pointing to API controller
3. Extract shared logic into service layer
4. Use content negotiation only where appropriate

## Controller Pairs to Consolidate

### 1. StrController
| Aspect | Web (`/StrController.php`) | API (`/Api/V1/StrController.php`) |
|--------|---------------------------|----------------------------------|
| Namespace | `App\Http\Controllers` | `App\Http\Controllers\Api\V1` |
| Return | Blade view | JsonResponse |
| Dependencies | StrReportService, AuditService, ComplianceService | StrReportService, AuditService |
| Pagination | Hardcoded 20 | `$request->get('per_page', 20)` |

### 2. CustomerController
| Aspect | Web | API |
|--------|-----|-----|
| Return | Blade view | JsonResponse |
| Duplicate logic | ~200 lines validation/encryption | Same |

### 3. AlertTriageController
| Aspect | Web | API |
|--------|-----|-----|
| Return | Blade view | JsonResponse |
| Duplicate logic | Queue summary, assignment | Same |

### 4. EnhancedDiligenceController
| Aspect | Web | API |
|--------|-----|-----|
| Return | Blade view | JsonResponse |
| Duplicate logic | EDD workflow | Same |

### 5. CaseManagementController
| Aspect | Web | API |
|--------|-----|-----|
| Return | Blade view | JsonResponse |
| Duplicate logic | Case creation, escalation | Same |

### 6. TransactionController
| Aspect | Web | API |
|--------|-----|-----|
| Return | Blade view | JsonResponse |
| Duplicate logic | ~100 lines | Same |

## Proposed Approach

### Option A: Service Extraction (Recommended)
Extract business logic to service layer, keep controllers thin:
- Web controller calls service, returns view
- API controller calls same service, returns json
- No duplication, clear separation

### Option B: Content Negotiation
Single controller with `request()->wantsJson()` check:
```php
public function index(Request $request) {
    $data = $this->service->getList($request->filters());
    if ($request->wantsJson()) {
        return response()->json(['data' => $data]);
    }
    return view('controller.index', ['records' => $data]);
}
```
Risk: Mixed responsibilities, harder to test

## Implementation Steps

### Phase 1: Service Extraction for StrController (Proof of Concept)

1. Create `StrService` with all business logic
   - Move `index()`, `store()`, `show()`, `submit()` logic from both controllers
   - Keep controllers as thin wrappers

2. Test thoroughly

3. Repeat for other pairs

### Phase 2: Rollout to Remaining Controllers

- CustomerController → CustomerService
- AlertTriageController → AlertTriageService
- EnhancedDiligenceController → EddService
- CaseManagementController → CaseManagementService (already exists!)
- TransactionController → TransactionService (already exists!)

## Critical Risks

1. **Breaking existing routes** - Must maintain route compatibility
2. **Losing web-specific functionality** - Flash messages, view data
3. **API response format changes** - Existing clients depend on current JSON structure
4. **Test coverage gaps** - Need comprehensive tests before refactoring

## Testing Requirements

Before refactoring each controller pair:
1. Identify all routes (web + API)
2. Document current request/response formats
3. Ensure tests cover both web and API paths
4. Run full test suite - must pass before and after

## Files to Modify

### Services (new or existing)
- `app/Services/StrService.php` (create or enhance)
- `app/Services/CustomerService.php` (create)
- `app/Services/AlertService.php` (create)
- `app/Services/EddService.php` (already exists)
- `app/Services/CaseService.php` (create or enhance existing)

### Controllers (modify, not delete)
- Keep both web and API controllers
- Reduce to thin wrappers calling services
- Maintain route compatibility

### Tests (may need updates)
- Ensure API and web paths both tested
- Add integration tests for content negotiation

## Timeline Estimate

- Phase 1 (StrController): 2-3 hours (proof of concept)
- Phase 2 (remaining 5): 4-6 hours
- Testing throughout

## Recommendation

Start with **StrController** as proof-of-concept since:
1. Simple enough to see pattern
2. Good test coverage exists
3. Lower risk if something breaks

Once StrController refactor is validated, proceed to remaining pairs.