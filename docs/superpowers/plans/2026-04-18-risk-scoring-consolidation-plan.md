# Risk Scoring Services Consolidation Plan

## Current State

Three services that appear to do similar work:

| Service | Namespace | Purpose |
|---------|-----------|---------|
| `UnifiedRiskScoringService` | `App\Services` | Factor-based risk scoring with events |
| `RiskScoringEngine` | `App\Services\Compliance` | Baseline deviation, geographic, PEP scoring |
| `CustomerRiskScoringService` | `App\Services` | Snapshot creation and trend calculation |

## Actual Responsibilities

### UnifiedRiskScoringService
- Main entry point for risk scoring
- Calculates weighted factor scores (velocity, structuring, geographic, etc.)
- Returns full risk profile array
- Fires `RiskScoreCalculated` event

### RiskScoringEngine
- Calculates deviation from customer's behavioral baseline
- Uses baseline data from `CustomerBehavioralBaseline` model
- Independent scoring engine (composition, not duplication)

### CustomerRiskScoringService
- Creates `RiskScoreSnapshot` records
- Calculates trends from historical snapshots
- Schedules next screening dates
- Wrapper around other scoring services

## Key Observation

These are **not duplicates** - they're composable:

```
CustomerRiskScoringService
    └── uses UnifiedRiskScoringService (for factor scores)
    └── uses RiskScoringEngine (for baseline deviation)
    └── creates RiskScoreSnapshot (persistence layer)
```

## Proposed Architecture

```php
class CustomerRiskScoringService
{
    public function __construct(
        protected UnifiedRiskScoringService $factorScorer,  // Factor scores
        protected RiskScoringEngine $deviationEngine,      // Deviation analysis
    ) {}

    public function assessCustomer(Customer $customer): RiskProfile
    {
        $factorScores = $this->factorScorer->calculateRiskScore($customer);
        $deviationScore = $this->deviationEngine->calculateScore($customer->id);
        // Combine and return unified profile
    }
}
```

## Implementation Steps

### Step 1: Document Current Usage

Search for which service is called where:
- Transaction flow → uses which?
- Customer creation → uses which?
- Scheduled jobs → uses which?

### Step 2: Identify Integration Points

Check how these services interact:
```php
// Current: CustomerRiskScoringService calls others
$this->screeningService->screenCustomer()  // UnifiedSanctionScreeningService
$this->complianceService->checkSanctionMatch()  // ComplianceService (now uses UnifiedSanctionScreeningService)
```

### Step 3: Refactor CustomerRiskScoringService

Current service has:
- `calculateAndSnapshot()` - main entry point
- `calculateRiskScores()` - calls UnifiedRiskScoringService
- `getRecentTransactions()` - data fetching
- `extractRiskFactors()` - factor extraction

Refactor to use RiskScoringEngine for deviation analysis.

### Step 4: Ensure Backward Compatibility

- `CustomerRiskScoringService::calculateRiskScores()` must still return same array structure
- `CustomerRiskScoringService::calculateAndSnapshot()` must still return `RiskScoreSnapshot`
- `UnifiedRiskScoringService::calculateRiskScore()` must still return same array keys

## Critical Risks

1. **API contract changes** - Return values must remain identical
2. **Event firing changes** - RiskScoreCalculated and RiskScoreUpdated events must still fire
3. **Missing factors** - Moving logic between services could lose edge cases

## Testing Requirements

Need tests that verify:
1. `CustomerRiskScoringService::calculateRiskScores()` returns expected structure
2. `CustomerRiskScoringService::calculateAndSnapshot()` creates valid snapshot
3. Events fire correctly
4. Trend calculation is accurate

## Files to Modify

### Services (refactor, not delete)
- `app/Services/CustomerRiskScoringService.php` - Enhance to compose other services
- `app/Services/UnifiedRiskScoringService.php` - Keep as factor scorer
- `app/Services/Compliance/RiskScoringEngine.php` - Keep as deviation engine

### Models (may need updates)
- `CustomerRiskProfile` - Check if still used
- `RiskScoreSnapshot` - Ensure compatibility

### Tests (may need updates)
- `tests/Unit/CustomerRiskScoringServiceTest.php` - Add tests for new composition
- `tests/Unit/UnifiedRiskScoringServiceTest.php` - Ensure factor scoring unchanged

## Recommendation

**Do NOT merge into one service.** The separation of concerns is valid:
- Factor scoring (UnifiedRiskScoringService) - what factors contribute
- Deviation analysis (RiskScoringEngine) - how customer compares to self
- Snapshot/trend (CustomerRiskScoringService) - historical tracking

Instead, refactor `CustomerRiskScoringService` to compose the other two rather than duplicate logic.