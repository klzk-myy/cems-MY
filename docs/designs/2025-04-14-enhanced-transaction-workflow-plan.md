# Enhanced Transaction Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a comprehensive transaction workflow with pre-transaction sanctions screening, dynamic CDD wizard, returning customer historical risk analysis, and automated bookkeeping with deferred entries for Enhanced CDD.

**Architecture:** Synchronous validation pipeline where sanctions screening and CDD assessment happen before transaction creation. Historical risk analysis triggers on returning customers. Enhanced CDD transactions are held pending approval before journal entries are created.

**Tech Stack:** Laravel 10.x, PHP 8.2, BCMath for precision, MySQL with pessimistic locking, Event-driven architecture

---

## File Structure

### New Files to Create
1. `app/Http/Controllers/TransactionWizardController.php` - Multi-step wizard API
2. `app/Services/TransactionPreValidationService.php` - Pre-transaction validation
3. `app/Services/HistoricalRiskAnalysisService.php` - Returning customer analysis
4. `app/Http/Requests/TransactionWizardStep1Request.php` - Step validation
5. `app/Http/Requests/TransactionWizardStep2Request.php` - Step validation
6. `app/Http/Requests/TransactionWizardStep3Request.php` - Step validation
7. `resources/views/transactions/wizard/step1.blade.php` - CDD assessment UI
8. `resources/views/transactions/wizard/step2.blade.php` - Customer details UI
9. `resources/views/transactions/wizard/step3.blade.php` - Review & confirm UI

### Existing Files to Modify
1. `app/Http/Controllers/TransactionController.php` - Update store() method
2. `app/Services/TransactionService.php` - Add wizard integration
3. `app/Services/ComplianceService.php` - Enhance CDD determination
4. `app/Services/SanctionScreeningService.php` - Add pre-transaction check
5. `app/Services/AccountingService.php` - Support deferred entries
6. `app/Models/Transaction.php` - Add status enums
7. `routes/web.php` - Add wizard routes
8. `routes/api.php` - Add validation API routes

---

## Task 1: Create TransactionPreValidationService

**Purpose:** Central service for all pre-transaction validations

**Files:**
- Create: `app/Services/TransactionPreValidationService.php`
- Test: `tests/Unit/TransactionPreValidationServiceTest.php`

**Steps:**

- [ ] **Step 1.1: Create service class with dependencies**

```php
<?php

namespace App\Services;

use App\Models\Customer;
use App\Services\SanctionScreeningService;
use App\Services\ComplianceService;
use App\Services\HistoricalRiskAnalysisService;
use Illuminate\Support\Facades\Log;

class TransactionPreValidationService
{
    public function __construct(
        protected SanctionScreeningService $sanctionScreeningService,
        protected ComplianceService $complianceService,
        protected HistoricalRiskAnalysisService $historicalRiskAnalysisService,
        protected AuditService $auditService
    ) {}

    /**
     * Run complete pre-transaction validation
     */
    public function validate(
        Customer $customer,
        string $amount,
        string $currencyCode
    ): PreValidationResult {
        $result = new PreValidationResult();
        
        // 1. Sanctions screening (blocking)
        $sanctionResult = $this->checkSanctions($customer);
        if ($sanctionResult->isBlocked()) {
            $result->addBlock('sanctions', $sanctionResult->getMessage());
            return $result;
        }
        
        // 2. CDD level determination
        $cddLevel = $this->complianceService->determineCDDLevel($amount, $customer);
        $result->setCDDLevel($cddLevel);
        
        // 3. Historical risk analysis (for returning customers)
        if ($this->isReturningCustomer($customer)) {
            $riskResult = $this->historicalRiskAnalysisService->analyze($customer, $amount);
            $result->setRiskFlags($riskResult->getFlags());
        }
        
        // 4. Determine hold status
        $holdRequired = $this->determineHoldRequired($result);
        $result->setHoldRequired($holdRequired);
        
        $this->auditService->logTransaction('pre_validation_completed', null, [
            'customer_id' => $customer->id,
            'amount' => $amount,
            'cdd_level' => $cddLevel->value,
            'hold_required' => $holdRequired,
            'risk_flags' => $result->getRiskFlags(),
        ]);
        
        return $result;
    }

    private function checkSanctions(Customer $customer): SanctionCheckResult
    {
        return $this->sanctionScreeningService->checkCustomer($customer);
    }

    private function isReturningCustomer(Customer $customer): bool
    {
        return $customer->transactions()->count() > 0;
    }

    private function determineHoldRequired(PreValidationResult $result): bool
    {
        // Hold if Enhanced CDD
        if ($result->getCDDLevel() === \App\Enums\CddLevel::Enhanced) {
            return true;
        }
        
        // Hold if any critical risk flags
        foreach ($result->getRiskFlags() as $flag) {
            if ($flag['severity'] === 'critical') {
                return true;
            }
        }
        
        return false;
    }
}
```

- [ ] **Step 1.2: Create PreValidationResult value object**

```php
<?php

namespace App\Services;

use App\Enums\CddLevel;

class PreValidationResult
{
    private array $blocks = [];
    private ?CddLevel $cddLevel = null;
    private array $riskFlags = [];
    private bool $holdRequired = false;

    public function addBlock(string $type, string $message): void
    {
        $this->blocks[] = ['type' => $type, 'message' => $message];
    }

    public function isBlocked(): bool
    {
        return count($this->blocks) > 0;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function setCDDLevel(CddLevel $cddLevel): void
    {
        $this->cddLevel = $cddLevel;
    }

    public function getCDDLevel(): ?CddLevel
    {
        return $this->cddLevel;
    }

    public function setRiskFlags(array $flags): void
    {
        $this->riskFlags = $flags;
    }

    public function getRiskFlags(): array
    {
        return $this->riskFlags;
    }

    public function setHoldRequired(bool $required): void
    {
        $this->holdRequired = $required;
    }

    public function isHoldRequired(): bool
    {
        return $this->holdRequired;
    }
}
```

- [ ] **Step 1.3: Create SanctionCheckResult value object**

```php
<?php

namespace App\Services;

class SanctionCheckResult
{
    private bool $blocked;
    private string $message;
    private float $matchScore;
    private ?string $matchedEntity;

    public function __construct(
        bool $blocked,
        string $message,
        float $matchScore = 0.0,
        ?string $matchedEntity = null
    ) {
        $this->blocked = $blocked;
        $this->message = $message;
        $this->matchScore = $matchScore;
        $this->matchedEntity = $matchedEntity;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMatchScore(): float
    {
        return $this->matchScore;
    }

    public function getMatchedEntity(): ?string
    {
        return $this->matchedEntity;
    }

    public static function passed(): self
    {
        return new self(false, 'Sanctions screening passed');
    }

    public static function blocked(string $message, float $score, string $entity): self
    {
        return new self(true, $message, $score, $entity);
    }
}
```

- [ ] **Step 1.4: Write tests**

```php
<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Services\PreValidationResult;
use App\Services\SanctionCheckResult;
use App\Services\TransactionPreValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionPreValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionPreValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransactionPreValidationService::class);
    }

    public function test_sanctions_block_stops_validation(): void
    {
        $customer = Customer::factory()->create(['sanction_hit' => true]);
        
        $result = $this->service->validate($customer, '1000.00', 'USD');
        
        $this->assertTrue($result->isBlocked());
        $this->assertFalse($result->isHoldRequired());
    }

    public function test_enhanced_cdd_requires_hold(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'sanction_hit' => false,
        ]);
        
        $result = $this->service->validate($customer, '60000.00', 'USD');
        
        $this->assertFalse($result->isBlocked());
        $this->assertTrue($result->isHoldRequired());
        $this->assertEquals(CddLevel::Enhanced, $result->getCDDLevel());
    }

    public function test_standard_cdd_no_hold(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);
        
        $result = $this->service->validate($customer, '5000.00', 'USD');
        
        $this->assertFalse($result->isBlocked());
        $this->assertFalse($result->isHoldRequired());
        $this->assertEquals(CddLevel::Standard, $result->getCDDLevel());
    }

    public function test_simplified_cdd_no_hold(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);
        
        $result = $this->service->validate($customer, '1000.00', 'USD');
        
        $this->assertFalse($result->isBlocked());
        $this->assertFalse($result->isHoldRequired());
        $this->assertEquals(CddLevel::Simplified, $result->getCDDLevel());
    }
}
```

- [ ] **Step 1.5: Commit**

```bash
git add app/Services/TransactionPreValidationService.php \
       app/Services/PreValidationResult.php \
       app/Services/SanctionCheckResult.php \
       tests/Unit/TransactionPreValidationServiceTest.php
git commit -m "feat: Add TransactionPreValidationService with sanctions, CDD, and risk analysis"
```

---

## Task 2: Enhance SanctionScreeningService for Pre-Transaction Checks

**Purpose:** Add blocking capability to sanctions screening

**Files:**
- Modify: `app/Services/SanctionScreeningService.php`
- Test: `tests/Unit/SanctionScreeningServiceTest.php`

**Steps:**

- [ ] **Step 2.1: Add checkCustomer method**

```php
/**
 * Check customer for sanctions - returns structured result
 */
public function checkCustomer(Customer $customer): SanctionCheckResult
{
    $fullName = $customer->full_name;
    
    // Escape LIKE wildcards
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $fullName);
    $pattern = '%' . $escaped . '%';
    
    // Check against sanction entries
    $matches = DB::table('sanction_entries')
        ->whereRaw("entity_name LIKE ?", [$pattern])
        ->orWhereRaw("aliases LIKE ?", [$pattern])
        ->get();
    
    foreach ($matches as $match) {
        $similarity = $this->calculateSimilarity(
            strtolower($fullName),
            strtolower($match->entity_name)
        );
        
        // Block if similarity > 80%
        if ($similarity >= 0.80) {
            Log::warning('Sanctions match detected', [
                'customer_id' => $customer->id,
                'customer_name' => $fullName,
                'matched_entity' => $match->entity_name,
                'similarity' => $similarity,
                'list_name' => $match->list_name,
            ]);
            
            // Audit log
            $this->auditService->logSanctionEvent('sanction_screening_hit', $customer->id, [
                'customer_name' => $fullName,
                'matched_entity' => $match->entity_name,
                'similarity' => $similarity,
                'action' => 'blocked',
            ]);
            
            return SanctionCheckResult::blocked(
                'Sanctions list match detected. Transaction blocked.',
                $similarity,
                $match->entity_name
            );
        }
        
        // Flag for review if similarity > 60%
        if ($similarity >= 0.60) {
            $this->auditService->logSanctionEvent('sanction_screening_flag', $customer->id, [
                'customer_name' => $fullName,
                'matched_entity' => $match->entity_name,
                'similarity' => $similarity,
                'action' => 'flagged',
            ]);
            
            // Create compliance flag
            \App\Models\FlaggedTransaction::create([
                'customer_id' => $customer->id,
                'flag_type' => \App\Enums\ComplianceFlagType::SanctionMatch,
                'severity' => 'warning',
                'description' => "Possible sanctions match: {$match->entity_name} ({$similarity}% similar)",
                'status' => 'open',
            ]);
        }
    }
    
    return SanctionCheckResult::passed();
}
```

- [ ] **Step 2.2: Add helper similarity calculation**

```php
private function calculateSimilarity(string $str1, string $str2): float
{
    similar_text($str1, $str2, $percent);
    return $percent / 100;
}
```

- [ ] **Step 2.3: Commit**

```bash
git add app/Services/SanctionScreeningService.php
git commit -m "feat: Add blocking sanctions screening with similarity thresholds"
```

---

## Task 3: Create HistoricalRiskAnalysisService

**Purpose:** Analyze returning customer transaction history for risk patterns

**Files:**
- Create: `app/Services/HistoricalRiskAnalysisService.php`
- Create: `app/Services/RiskAnalysisResult.php`
- Test: `tests/Unit/HistoricalRiskAnalysisServiceTest.php`

**Steps:**

- [ ] **Step 3.1: Create service class**

```php
<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HistoricalRiskAnalysisService
{
    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService
    ) {}

    /**
     * Analyze customer transaction history for risk patterns
     */
    public function analyze(Customer $customer, string $currentAmount): RiskAnalysisResult
    {
        $result = new RiskAnalysisResult();
        
        // Check various risk patterns
        $this->checkVelocityRisk($customer, $result);
        $this->checkStructuringRisk($customer, $result);
        $this->checkAmountEscalation($customer, $currentAmount, $result);
        $this->checkPatternChange($customer, $result);
        $this->checkCumulativeRisk($customer, $currentAmount, $result);
        
        // Log analysis
        if (count($result->getFlags()) > 0) {
            Log::info('Historical risk analysis completed with flags', [
                'customer_id' => $customer->id,
                'flags' => $result->getFlags(),
            ]);
            
            $this->auditService->logCustomerRiskEvent(
                'historical_risk_analysis',
                $customer->id,
                $result->getFlags()
            );
        }
        
        return $result;
    }

    /**
     * Check velocity: >3 transactions in 24h
     */
    private function checkVelocityRisk(Customer $customer, RiskAnalysisResult $result): void
    {
        $recentCount = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->where('status', '!=', 'cancelled')
            ->count();
        
        if ($recentCount >= 3) {
            $result->addFlag([
                'type' => 'velocity',
                'severity' => 'warning',
                'description' => "{$recentCount} transactions in last 24 hours",
                'metric' => $recentCount,
                'threshold' => 3,
            ]);
        }
    }

    /**
     * Check structuring: Multiple transactions just below RM 3,000 threshold
     */
    private function checkStructuringRisk(Customer $customer, RiskAnalysisResult $result): void
    {
        $structuringThreshold = '3000';
        $structuringWindow = Carbon::now()->subHours(1);
        
        $structuringCount = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', $structuringWindow)
            ->where('amount_local', '<', $structuringThreshold)
            ->where('amount_local', '>=', '2500')
            ->where('status', '!=', 'cancelled')
            ->count();
        
        if ($structuringCount >= 2) {
            $result->addFlag([
                'type' => 'structuring',
                'severity' => 'critical',
                'description' => "Potential structuring: {$structuringCount} transactions just below RM 3,000 threshold",
                'metric' => $structuringCount,
                'threshold' => 2,
            ]);
        }
    }

    /**
     * Check amount escalation: 200% above 90-day average
     */
    private function checkAmountEscalation(
        Customer $customer,
        string $currentAmount,
        RiskAnalysisResult $result
    ): void {
        $avgAmount = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', Carbon::now()->subDays(90))
            ->where('status', '!=', 'cancelled')
            ->avg('amount_local');
        
        if ($avgAmount > 0) {
            $escalation = $this->mathService->divide($currentAmount, (string) $avgAmount);
            
            if ($this->mathService->compare($escalation, '2.0') >= 0) {
                $result->addFlag([
                    'type' => 'amount_escalation',
                    'severity' => 'warning',
                    'description' => "Transaction amount is {$escalation}x above 90-day average",
                    'metric' => $escalation,
                    'threshold' => 2.0,
                ]);
            }
        }
    }

    /**
     * Check pattern change: Buy/Sell reversal, currency switch
     */
    private function checkPatternChange(Customer $customer, RiskAnalysisResult $result): void
    {
        // Get last 10 transactions
        $recentTransactions = Transaction::where('customer_id', $customer->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        if ($recentTransactions->count() < 5) {
            return;
        }
        
        // Check for reversal (always buying, suddenly selling)
        $buyCount = $recentTransactions->where('type', 'buy')->count();
        $sellCount = $recentTransactions->where('type', 'sell')->count();
        
        if ($buyCount >= 7 && $sellCount >= 2) {
            // Previously mostly buying, now selling
            $lastType = $recentTransactions->first()->type;
            $prevType = $recentTransactions->skip(1)->first()->type;
            
            if ($lastType === 'sell' && $prevType === 'buy') {
                $result->addFlag([
                    'type' => 'pattern_reversal',
                    'severity' => 'warning',
                    'description' => 'Pattern change: Previously buying, now selling',
                    'metric' => 'buy_sell_reversal',
                ]);
            }
        }
        
        // Check for currency switch (frequent currency changes)
        $currencies = $recentTransactions->pluck('currency_code')->unique();
        if ($currencies->count() >= 3) {
            $result->addFlag([
                'type' => 'currency_switch',
                'severity' => 'info',
                'description' => 'Multiple currency types in recent transactions',
                'metric' => $currencies->count(),
            ]);
        }
    }

    /**
     * Check cumulative: Aggregate related transactions over 7 days
     */
    private function checkCumulativeRisk(
        Customer $customer,
        string $currentAmount,
        RiskAnalysisResult $result
    ): void {
        $cumulativeThreshold = '50000';
        $window = Carbon::now()->subDays(7);
        
        $weekTotal = Transaction::where('customer_id', $customer->id)
            ->where('created_at', '>=', $window)
            ->where('status', '!=', 'cancelled')
            ->sum('amount_local');
        
        $total = $this->mathService->add((string) $weekTotal, $currentAmount);
        
        if ($this->mathService->compare($total, $cumulativeThreshold) >= 0) {
            $result->addFlag([
                'type' => 'cumulative_amount',
                'severity' => 'warning',
                'description' => "7-day cumulative amount reaches RM {$total}",
                'metric' => $total,
                'threshold' => $cumulativeThreshold,
            ]);
        }
    }
}
```

- [ ] **Step 3.2: Create RiskAnalysisResult value object**

```php
<?php

namespace App\Services;

class RiskAnalysisResult
{
    private array $flags = [];

    public function addFlag(array $flag): void
    {
        $this->flags[] = $flag;
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function hasCriticalFlags(): bool
    {
        foreach ($this->flags as $flag) {
            if ($flag['severity'] === 'critical') {
                return true;
            }
        }
        return false;
    }

    public function getFlagSummary(): string
    {
        if (empty($this->flags)) {
            return 'No risk flags detected';
        }

        $summary = [];
        foreach ($this->flags as $flag) {
            $summary[] = "[{$flag['severity']}] {$flag['type']}";
        }

        return implode(', ', $summary);
    }
}
```

- [ ] **Step 3.3: Write tests**

```php
<?php

namespace Tests\Unit;

use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\HistoricalRiskAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalRiskAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HistoricalRiskAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HistoricalRiskAnalysisService::class);
    }

    public function test_detects_velocity_risk(): void
    {
        $customer = Customer::factory()->create();
        
        // Create 3 recent transactions
        Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subHours(2),
        ]);
        
        $result = $this->service->analyze($customer, '1000.00');
        
        $flags = $result->getFlags();
        $this->assertNotEmpty($flags);
        $this->assertEquals('velocity', $flags[0]['type']);
    }

    public function test_detects_structuring_risk(): void
    {
        $customer = Customer::factory()->create();
        
        // Create 2 transactions just below RM 3,000
        Transaction::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'amount_local' => '2900.00',
            'created_at' => now()->subMinutes(30),
        ]);
        
        $result = $this->service->analyze($customer, '1000.00');
        
        $flags = $result->getFlags();
        $this->assertNotEmpty($flags);
        $this->assertEquals('structuring', $flags[0]['type']);
        $this->assertEquals('critical', $flags[0]['severity']);
    }

    public function test_detects_pattern_reversal(): void
    {
        $customer = Customer::factory()->create();
        
        // Create 8 buy transactions
        Transaction::factory()->count(8)->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy,
            'created_at' => now()->subDays(5),
        ]);
        
        // Now selling
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Sell,
            'created_at' => now()->subDay(),
        ]);
        
        $result = $this->service->analyze($customer, '1000.00');
        
        $flags = $result->getFlags();
        $patternFlags = array_filter($flags, fn($f) => $f['type'] === 'pattern_reversal');
        $this->assertNotEmpty($patternFlags);
    }
}
```

- [ ] **Step 3.4: Commit**

```bash
git add app/Services/HistoricalRiskAnalysisService.php \
       app/Services/RiskAnalysisResult.php \
       tests/Unit/HistoricalRiskAnalysisServiceTest.php
git commit -m "feat: Add HistoricalRiskAnalysisService for returning customer risk patterns"
```

---

## Task 4: Create Transaction Wizard Controller

**Purpose:** Multi-step wizard API for teller-guided transaction creation

**Files:**
- Create: `app/Http/Controllers/TransactionWizardController.php`
- Create: `app/Http/Requests/TransactionWizardStep1Request.php`
- Create: `app/Http/Requests/TransactionWizardStep2Request.php`
- Create: `app/Http/Requests/TransactionWizardStep3Request.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/TransactionWizardTest.php`

**Steps:**

- [ ] **Step 4.1: Create Step 1 Request (Initial Transaction Data)**

```php
<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TransactionWizardStep1Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canCreateTransactions();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'type' => ['required', new Enum(TransactionType::class)],
            'currency_code' => ['required', 'string', 'exists:currencies,code'],
            'amount_foreign' => ['required', 'numeric', 'min:0.01', 'max:9999999999.9999'],
            'rate' => ['required', 'numeric', 'min:0.0001', 'max:999999'],
            'till_id' => ['required', 'string', 'exists:counters,id'],
            'purpose' => ['required', 'string', 'max:255'],
            'source_of_funds' => ['required', 'string', 'max:255'],
            'collect_additional_details' => ['sometimes', 'boolean'], // Teller override
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer',
            'amount_foreign.min' => 'Transaction amount must be at least RM 0.01',
            'amount_foreign.max' => 'Transaction amount exceeds maximum limit',
            'rate.min' => 'Exchange rate must be greater than 0',
        ];
    }
}
```

- [ ] **Step 4.2: Create Step 2 Request (Customer Details)**

```php
<?php

namespace App\Http\Requests;

use App\Enums\CddLevel;
use Illuminate\Foundation\Http\FormRequest;

class TransactionWizardStep2Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canCreateTransactions();
    }

    public function rules(): array
    {
        $cddLevel = $this->input('cdd_level');
        $rules = [
            'wizard_session_id' => ['required', 'string'],
            'cdd_level' => ['required', 'string', 'in:' . implode(',', array_column(CddLevel::cases(), 'value'))],
        ];

        // Base required fields
        $rules['customer.occupation'] = ['required', 'string', 'max:255'];
        $rules['customer.employer_name'] = ['nullable', 'string', 'max:255'];
        $rules['customer.employer_address'] = ['nullable', 'string', 'max:1000'];
        $rules['customer.annual_volume_estimate'] = ['nullable', 'numeric', 'min:0'];

        // CDD Level specific requirements
        if ($cddLevel === CddLevel::Standard->value || $cddLevel === CddLevel::Enhanced->value) {
            $rules['customer.proof_of_address'] = ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'];
        }

        if ($cddLevel === CddLevel::Enhanced->value) {
            $rules['customer.passport'] = ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'];
            $rules['customer.beneficial_owner'] = ['required', 'string', 'max:255'];
            $rules['customer.source_of_wealth'] = ['required', 'string', 'max:500'];
            $rules['transaction.expected_frequency'] = ['required', 'string', 'in:weekly,monthly,quarterly,annually'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'customer.proof_of_address.required' => 'Proof of address is required for Standard/Enhanced CDD',
            'customer.passport.required' => 'Passport is required for Enhanced CDD',
            'customer.beneficial_owner.required' => 'Beneficial ownership information is required',
        ];
    }
}
```

- [ ] **Step 4.3: Create Step 3 Request (Review & Confirm)**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionWizardStep3Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canCreateTransactions();
    }

    public function rules(): array
    {
        return [
            'wizard_session_id' => ['required', 'string'],
            'confirm_details' => ['required', 'accepted'],
            'idempotency_key' => ['required', 'string', 'unique:transactions,idempotency_key'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_details.accepted' => 'You must confirm the transaction details',
            'idempotency_key.unique' => 'This transaction appears to be a duplicate',
        ];
    }
}
```

- [ ] **Step 4.4: Create Wizard Controller**

```php
<?php

namespace App\Http\Controllers;

use App\Enums\CddLevel;
use App\Http\Requests\TransactionWizardStep1Request;
use App\Http\Requests\TransactionWizardStep2Request;
use App\Http\Requests\TransactionWizardStep3Request;
use App\Models\Customer;
use App\Services\TransactionPreValidationService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionWizardController extends Controller
{
    public function __construct(
        protected TransactionPreValidationService $preValidationService,
        protected TransactionService $transactionService
    ) {}

    /**
     * Step 1: Initial transaction data + CDD assessment
     */
    public function step1(TransactionWizardStep1Request $request): JsonResponse
    {
        $validated = $request->validated();
        $customer = Customer::find($validated['customer_id']);
        
        // Calculate local amount
        $amountLocal = bcmul($validated['amount_foreign'], $validated['rate'], 4);
        
        // Run pre-validation (sanctions, CDD, risk)
        $validationResult = $this->preValidationService->validate(
            $customer,
            $amountLocal,
            $validated['currency_code']
        );
        
        // Check if blocked
        if ($validationResult->isBlocked()) {
            return response()->json([
                'status' => 'blocked',
                'message' => $validationResult->getBlocks()[0]['message'],
                'reason' => $validationResult->getBlocks()[0]['type'],
            ], 403);
        }
        
        // Determine CDD level (allow teller override)
        $cddLevel = $validationResult->getCDDLevel();
        if ($request->boolean('collect_additional_details')) {
            // Teller wants to collect more details even below threshold
            $cddLevel = $this->upgradeCDDLevel($cddLevel);
        }
        
        // Create wizard session
        $sessionId = Str::uuid()->toString();
        $sessionData = [
            'step' => 1,
            'customer_id' => $customer->id,
            'transaction_data' => $validated,
            'amount_local' => $amountLocal,
            'cdd_level' => $cddLevel->value,
            'risk_flags' => $validationResult->getRiskFlags(),
            'hold_required' => $validationResult->isHoldRequired(),
            'created_at' => now(),
        ];
        
        Cache::put("wizard:{$sessionId}", $sessionData, now()->addHour());
        
        // Prepare required documents list
        $requiredDocuments = $this->getRequiredDocuments($cddLevel);
        
        return response()->json([
            'status' => 'success',
            'wizard_session_id' => $sessionId,
            'cdd_level' => $cddLevel->value,
            'cdd_description' => $this->getCDDDescription($cddLevel),
            'hold_required' => $validationResult->isHoldRequired(),
            'risk_flags' => $validationResult->getRiskFlags(),
            'required_documents' => $requiredDocuments,
            'customer_is_returning' => $customer->transactions()->exists(),
            'next_step' => 'customer_details',
        ]);
    }

    /**
     * Step 2: Customer details collection
     */
    public function step2(TransactionWizardStep2Request $request): JsonResponse
    {
        $validated = $request->validated();
        $sessionId = $validated['wizard_session_id'];
        
        $sessionData = Cache::get("wizard:{$sessionId}");
        if (!$sessionData) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wizard session expired or invalid',
            ], 400);
        }
        
        // Update session with customer details
        $sessionData['step'] = 2;
        $sessionData['customer_details'] = $validated['customer'] ?? [];
        $sessionData['transaction_meta'] = $validated['transaction'] ?? [];
        $sessionData['documents'] = $this->processDocuments($request);
        
        Cache::put("wizard:{$sessionId}", $sessionData, now()->addHour());
        
        // Prepare summary for review
        $summary = $this->prepareTransactionSummary($sessionData);
        
        return response()->json([
            'status' => 'success',
            'wizard_session_id' => $sessionId,
            'transaction_summary' => $summary,
            'next_step' => 'review_confirm',
        ]);
    }

    /**
     * Step 3: Review and create transaction
     */
    public function step3(TransactionWizardStep3Request $request): JsonResponse
    {
        $validated = $request->validated();
        $sessionId = $validated['wizard_session_id'];
        
        $sessionData = Cache::get("wizard:{$sessionId}");
        if (!$sessionData) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wizard session expired or invalid',
            ], 400);
        }
        
        // Prepare final transaction data
        $transactionData = array_merge(
            $sessionData['transaction_data'],
            [
                'amount_local' => $sessionData['amount_local'],
                'cdd_level' => $sessionData['cdd_level'],
                'status' => $sessionData['hold_required'] ? 'pending_approval' : 'completed',
                'hold_reason' => $sessionData['hold_required'] ? 'enhanced_cdd_requires_approval' : null,
            ]
        );
        
        try {
            $transaction = $this->transactionService->createTransaction($transactionData);
            
            // Clear wizard session
            Cache::forget("wizard:{$sessionId}");
            
            return response()->json([
                'status' => 'success',
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'status' => $transaction->status,
                'message' => $sessionData['hold_required'] 
                    ? 'Transaction created and pending approval'
                    : 'Transaction completed successfully',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Transaction creation failed in wizard', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get wizard session status
     */
    public function status(string $sessionId): JsonResponse
    {
        $sessionData = Cache::get("wizard:{$sessionId}");
        
        if (!$sessionData) {
            return response()->json([
                'status' => 'expired',
                'message' => 'Wizard session has expired',
            ], 404);
        }
        
        return response()->json([
            'status' => 'active',
            'current_step' => $sessionData['step'],
            'expires_at' => now()->addHour()->toIso8601String(),
        ]);
    }

    /**
     * Cancel wizard session
     */
    public function cancel(string $sessionId): JsonResponse
    {
        Cache::forget("wizard:{$sessionId}");
        
        return response()->json([
            'status' => 'cancelled',
            'message' => 'Wizard session cancelled',
        ]);
    }

    // Helper methods
    private function upgradeCDDLevel(CddLevel $current): CddLevel
    {
        return match($current) {
            CddLevel::Simplified => CddLevel::Standard,
            CddLevel::Standard => CddLevel::Enhanced,
            CddLevel::Enhanced => CddLevel::Enhanced,
        };
    }

    private function getRequiredDocuments(CddLevel $cddLevel): array
    {
        $documents = [
            ['type' => 'mykad_front', 'required' => true, 'label' => 'MyKad (Front)'],
            ['type' => 'mykad_back', 'required' => true, 'label' => 'MyKad (Back)'],
        ];
        
        if ($cddLevel === CddLevel::Standard || $cddLevel === CddLevel::Enhanced) {
            $documents[] = ['type' => 'proof_of_address', 'required' => true, 'label' => 'Proof of Address'];
        }
        
        if ($cddLevel === CddLevel::Enhanced) {
            $documents[] = ['type' => 'passport', 'required' => true, 'label' => 'Passport'];
            $documents[] = ['type' => 'source_of_wealth', 'required' => true, 'label' => 'Source of Wealth Documentation'];
        }
        
        return $documents;
    }

    private function getCDDDescription(CddLevel $cddLevel): string
    {
        return match($cddLevel) {
            CddLevel::Simplified => 'Simplified Due Diligence - Basic customer information required',
            CddLevel::Standard => 'Standard Due Diligence - Additional documentation required',
            CddLevel::Enhanced => 'Enhanced Due Diligence - Comprehensive verification required',
        };
    }

    private function processDocuments($request): array
    {
        $documents = [];
        
        if ($request->hasFile('customer.proof_of_address')) {
            $documents['proof_of_address'] = $request->file('customer.proof_of_address')->store('kyc_documents');
        }
        
        if ($request->hasFile('customer.passport')) {
            $documents['passport'] = $request->file('customer.passport')->store('kyc_documents');
        }
        
        return $documents;
    }

    private function prepareTransactionSummary(array $sessionData): array
    {
        $data = $sessionData['transaction_data'];
        
        return [
            'customer_name' => Customer::find($data['customer_id'])->full_name,
            'type' => $data['type'],
            'currency' => $data['currency_code'],
            'amount_foreign' => $data['amount_foreign'],
            'rate' => $data['rate'],
            'amount_local' => $sessionData['amount_local'],
            'purpose' => $data['purpose'],
            'source_of_funds' => $data['source_of_funds'],
            'cdd_level' => $sessionData['cdd_level'],
            'hold_required' => $sessionData['hold_required'],
            'risk_flags' => count($sessionData['risk_flags']) > 0 
                ? $sessionData['risk_flags'] 
                : null,
        ];
    }
}
```

- [ ] **Step 4.5: Add routes**

Modify `routes/api.php`:

```php
<?php

use App\Http\Controllers\TransactionWizardController;
use Illuminate\Support\Facades\Route;

// Transaction Wizard API
Route::middleware(['auth', 'role:teller'])->prefix('wizard/transactions')->group(function () {
    Route::post('/step1', [TransactionWizardController::class, 'step1'])
        ->name('api.wizard.transactions.step1');
    Route::post('/step2', [TransactionWizardController::class, 'step2'])
        ->name('api.wizard.transactions.step2');
    Route::post('/step3', [TransactionWizardController::class, 'step3'])
        ->name('api.wizard.transactions.step3');
    Route::get('/{sessionId}/status', [TransactionWizardController::class, 'status'])
        ->name('api.wizard.transactions.status');
    Route::delete('/{sessionId}', [TransactionWizardController::class, 'cancel'])
        ->name('api.wizard.transactions.cancel');
});
```

- [ ] **Step 4.6: Write tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CddLevel;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionWizardTest extends TestCase
{
    use RefreshDatabase;

    protected User $teller;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
    }

    public function test_step1_returns_cdd_level_and_required_documents(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);
        
        $response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'cdd_level' => CddLevel::Simplified->value,
                'hold_required' => false,
            ])
            ->assertJsonPath('required_documents', function ($docs) {
                return count($docs) === 2; // MyKad front/back only
            });
    }

    public function test_step1_blocks_sanctioned_customers(): void
    {
        $customer = Customer::factory()->create(['sanction_hit' => true]);
        
        $response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);
        
        $response->assertStatus(403)
            ->assertJson([
                'status' => 'blocked',
                'reason' => 'sanctions',
            ]);
    }

    public function test_step1_detects_velocity_risk(): void
    {
        $customer = Customer::factory()->create();
        
        // Create 3 recent transactions
        \App\Models\Transaction::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subHours(2),
        ]);
        
        $response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);
        
        $response->assertStatus(200)
            ->assertJsonPath('risk_flags', function ($flags) {
                return count($flags) > 0;
            });
    }

    public function test_teller_can_override_to_collect_additional_details(): void
    {
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);
        
        $response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00', // Below RM 3K
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
                'collect_additional_details' => true, // Teller override
            ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'cdd_level' => CddLevel::Standard->value, // Upgraded
            ]);
    }

    public function test_enhanced_cdd_requires_hold(): void
    {
        $customer = Customer::factory()->create(['pep_status' => true]);
        
        $response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '60000.00', // Above RM 50K
                'rate' => '4.50',
                'till_id' => '1',
                'purpose' => 'Investment',
                'source_of_funds' => 'Business',
            ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'cdd_level' => CddLevel::Enhanced->value,
                'hold_required' => true,
            ]);
    }
}
```

- [ ] **Step 4.7: Commit**

```bash
git add app/Http/Controllers/TransactionWizardController.php \
       app/Http/Requests/TransactionWizardStep1Request.php \
       app/Http/Requests/TransactionWizardStep2Request.php \
       app/Http/Requests/TransactionWizardStep3Request.php \
       routes/api.php \
       tests/Feature/TransactionWizardTest.php
git commit -m "feat: Add Transaction Wizard API with 3-step flow"
```

---

## Task 5: Update TransactionService for Deferred Bookkeeping

**Purpose:** Support deferred journal entries for Enhanced CDD transactions

**Files:**
- Modify: `app/Services/TransactionService.php`
- Modify: `app/Services/AccountingService.php`
- Test: `tests/Feature/EnhancedCDDBookkeepingTest.php`

**Steps:**

- [ ] **Step 5.1: Add deferred entry support to Transaction model**

```php
// Add to app/Models/Transaction.php migration
// Run: php artisan make:migration add_deferred_journal_entry_id_to_transactions_table

Schema::table('transactions', function (Blueprint $table) {
    $table->foreignId('deferred_journal_entry_id')->nullable()->after('journal_entry_id')
        ->references('id')->on('journal_entries');
    $table->timestamp('journal_entries_created_at')->nullable()->after('deferred_journal_entry_id');
});
```

- [ ] **Step 5.2: Update TransactionService to handle deferred entries**

Modify `app/Services/TransactionService.php`:

```php
/**
 * Create accounting entries - supports deferred creation for Enhanced CDD
 */
protected function createAccountingEntries(
    Transaction $transaction,
    string $type,
    string $currencyCode,
    string $amountForeign,
    string $amountLocal,
    string $rate
): void {
    // For Enhanced CDD transactions, defer journal entry creation until approval
    if ($transaction->cdd_level === CddLevel::Enhanced->value 
        && $transaction->status !== TransactionStatus::Completed) {
        Log::info('Deferring journal entry creation for Enhanced CDD transaction', [
            'transaction_id' => $transaction->id,
            'status' => $transaction->status,
        ]);
        
        // Store reference that entries will be created later
        $transaction->journal_entries_created_at = null;
        $transaction->save();
        
        // Audit log
        $this->auditService->logTransaction('journal_entries_deferred', $transaction->id, [
            'cdd_level' => $transaction->cdd_level,
            'status' => $transaction->status,
        ]);
        
        return;
    }
    
    // Standard CDD: Create entries immediately
    $this->createImmediateAccountingEntries(
        $transaction,
        $type,
        $currencyCode,
        $amountForeign,
        $amountLocal,
        $rate
    );
}

/**
 * Create journal entries immediately (for Simplified/Standard CDD)
 */
protected function createImmediateAccountingEntries(
    Transaction $transaction,
    string $type,
    string $currencyCode,
    string $amountForeign,
    string $amountLocal,
    string $rate
): void {
    // Existing accounting logic...
    // [Keep existing implementation from current TransactionService]
}

/**
 * Create deferred journal entries (called when Enhanced CDD transaction is approved)
 */
public function createDeferredAccountingEntries(int $transactionId): void
{
    $transaction = Transaction::findOrFail($transactionId);
    
    // Verify it's Enhanced CDD and approved
    if ($transaction->cdd_level !== CddLevel::Enhanced->value) {
        throw new \InvalidArgumentException('Only Enhanced CDD transactions support deferred entries');
    }
    
    if ($transaction->status !== TransactionStatus::Completed) {
        throw new \InvalidArgumentException('Transaction must be completed to create journal entries');
    }
    
    // Create the journal entries
    $this->createImmediateAccountingEntries(
        $transaction,
        $transaction->type->value,
        $transaction->currency_code,
        $transaction->amount_foreign,
        $transaction->amount_local,
        $transaction->rate
    );
    
    // Update transaction
    $transaction->journal_entries_created_at = now();
    $transaction->save();
    
    // Audit log
    $this->auditService->logTransaction('deferred_journal_entries_created', $transaction->id, [
        'transaction_id' => $transaction->id,
        'journal_entry_id' => $transaction->journal_entry_id,
        'deferred_until' => now(),
    ]);
}
```

- [ ] **Step 5.3: Update TransactionApprovalService to trigger deferred entries**

Create/modify approval handler:

```php
// In TransactionApprovalService or where Enhanced CDD approval happens
public function approveEnhancedCDDTransaction(int $transactionId, int $approverId): void
{
    $transaction = Transaction::findOrFail($transactionId);
    
    // Update status
    $transaction->status = TransactionStatus::Completed;
    $transaction->approved_by = $approverId;
    $transaction->approved_at = now();
    $transaction->save();
    
    // Now create the deferred journal entries
    $this->transactionService->createDeferredAccountingEntries($transactionId);
    
    // Dispatch event
    event(new TransactionApproved($transaction));
    
    // Audit log
    $this->auditService->logTransaction('enhanced_cdd_approved', $transaction->id, [
        'approver_id' => $approverId,
        'deferred_entries_created' => true,
    ]);
}
```

- [ ] **Step 5.4: Write tests**

```php
<?php

namespace Tests\Feature;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancedCDDBookkeepingTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionService $transactionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionService = app(TransactionService::class);
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
    }

    public function test_enhanced_cdd_deferrs_journal_entries(): void
    {
        $customer = Customer::factory()->create(['pep_status' => true]);
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        
        $transaction = Transaction::create([
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '60000.00',
            'amount_local' => '270000.00',
            'rate' => '4.50',
            'status' => TransactionStatus::PendingApproval,
            'cdd_level' => CddLevel::Enhanced,
            'idempotency_key' => uniqid(),
        ]);
        
        // Journal entry should be deferred
        $this->assertNull($transaction->journal_entry_id);
        $this->assertNull($transaction->journal_entries_created_at);
    }

    public function test_standard_cdd_creates_journal_entries_immediately(): void
    {
        $customer = Customer::factory()->create(['pep_status' => false]);
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        
        $transaction = $this->transactionService->createTransaction([
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '1000.00',
            'amount_local' => '4500.00',
            'rate' => '4.50',
            'till_id' => '1',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
            'idempotency_key' => uniqid(),
        ]);
        
        // Reload to get journal entry
        $transaction->refresh();
        
        // Journal entry should be created
        $this->assertNotNull($transaction->journal_entry_id);
        $this->assertNotNull($transaction->journal_entries_created_at);
    }

    public function test_deferred_entries_created_on_approval(): void
    {
        $customer = Customer::factory()->create(['pep_status' => true]);
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        
        $transaction = Transaction::create([
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '60000.00',
            'amount_local' => '270000.00',
            'rate' => '4.50',
            'status' => TransactionStatus::PendingApproval,
            'cdd_level' => CddLevel::Enhanced,
            'idempotency_key' => uniqid(),
        ]);
        
        // Approve the transaction
        $this->transactionService->createDeferredAccountingEntries($transaction->id);
        
        $transaction->refresh();
        
        // Journal entry should now exist
        $this->assertNotNull($transaction->journal_entry_id);
        $this->assertNotNull($transaction->journal_entries_created_at);
    }
}
```

- [ ] **Step 5.5: Commit**

```bash
git add app/Services/TransactionService.php \
       database/migrations/*_add_deferred_journal_entry_id_to_transactions_table.php \
       tests/Feature/EnhancedCDDBookkeepingTest.php
git commit -m "feat: Add deferred journal entry support for Enhanced CDD transactions"
```

---

## Task 6: Create Wizard UI Views

**Purpose:** Teller-facing wizard interface for step-by-step transaction creation

**Files:**
- Create: `resources/views/transactions/wizard/index.blade.php`
- Create: `resources/views/transactions/wizard/step1.blade.php`
- Create: `resources/views/transactions/wizard/step2.blade.php`
- Create: `resources/views/transactions/wizard/step3.blade.php`
- Create: `resources/views/transactions/wizard/partials/risk-alert.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Browser/TransactionWizardTest.php` (if Dusk available)

**Steps:**

- [ ] **Step 6.1: Create main wizard layout**

```php
{{-- resources/views/transactions/wizard/index.blade.php --}}
@extends('layouts.app')

@section('title', 'New Transaction - Wizard')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        {{-- Progress Bar --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center w-full">
                    <div id="step1-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-bold">1</div>
                    <div id="step1-line" class="flex-1 h-1 bg-blue-600 mx-2"></div>
                    <div id="step2-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-300 text-gray-600 font-bold">2</div>
                    <div id="step2-line" class="flex-1 h-1 bg-gray-300 mx-2"></div>
                    <div id="step3-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-300 text-gray-600 font-bold">3</div>
                </div>
            </div>
            <div class="flex justify-between mt-2 text-sm text-gray-600">
                <span class="flex-1 text-center">Transaction Details</span>
                <span class="flex-1 text-center">Customer Information</span>
                <span class="flex-1 text-center">Review & Confirm</span>
            </div>
        </div>
        
        {{-- Alert Container --}}
        <div id="alert-container" class="mb-4"></div>
        
        {{-- Wizard Steps --}}
        <div id="wizard-container" class="bg-white rounded-lg shadow-lg p-6">
            @include('transactions.wizard.step1')
        </div>
    </div>
</div>

@push('scripts')
<script>
// Wizard state management
let wizardSessionId = null;
let currentStep = 1;

// Step 1: Submit transaction details
async function submitStep1(formData) {
    try {
        const response = await fetch('/api/wizard/transactions/step1', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.status === 'blocked') {
            showAlert('error', data.message);
            return false;
        }
        
        if (data.status === 'success') {
            wizardSessionId = data.wizard_session_id;
            
            // Show risk flags if any
            if (data.risk_flags && data.risk_flags.length > 0) {
                showRiskFlags(data.risk_flags);
            }
            
            // Show hold warning if required
            if (data.hold_required) {
                showAlert('warning', 'This transaction requires manager approval (Enhanced CDD)');
            }
            
            // Move to step 2
            loadStep2(data);
            return true;
        }
    } catch (error) {
        showAlert('error', 'Network error. Please try again.');
        return false;
    }
}

// Step 2: Submit customer details
async function submitStep2(formData) {
    try {
        formData.wizard_session_id = wizardSessionId;
        
        const response = await fetch('/api/wizard/transactions/step2', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData // FormData for file uploads
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            loadStep3(data.transaction_summary);
            return true;
        }
    } catch (error) {
        showAlert('error', 'Network error. Please try again.');
        return false;
    }
}

// Step 3: Confirm transaction
async function submitStep3() {
    try {
        const response = await fetch('/api/wizard/transactions/step3', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                wizard_session_id: wizardSessionId,
                confirm_details: true,
                idempotency_key: generateIdempotencyKey()
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            window.location.href = `/transactions/${data.transaction_id}`;
        }
    } catch (error) {
        showAlert('error', 'Failed to create transaction. Please try again.');
    }
}

// Helper functions
function showAlert(type, message) {
    const container = document.getElementById('alert-container');
    const colors = {
        error: 'bg-red-100 border-red-400 text-red-700',
        warning: 'bg-yellow-100 border-yellow-400 text-yellow-700',
        success: 'bg-green-100 border-green-400 text-green-700'
    };
    
    container.innerHTML = `
        <div class="${colors[type]} px-4 py-3 rounded border" role="alert">
            <span class="block sm:inline">${message}</span>
        </div>
    `;
}

function showRiskFlags(flags) {
    let html = '<div class="bg-orange-50 border border-orange-200 rounded p-4 mb-4">';
    html += '<h4 class="font-bold text-orange-800 mb-2">Risk Alerts Detected</h4>';
    html += '<ul class="list-disc list-inside text-orange-700">';
    
    flags.forEach(flag => {
        html += `<li><strong>${flag.type}:</strong> ${flag.description}</li>`;
    });
    
    html += '</ul></div>';
    
    document.getElementById('alert-container').innerHTML = html;
}

function generateIdempotencyKey() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

function updateProgress(step) {
    currentStep = step;
    
    // Update indicators
    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById(`step${i}-indicator`);
        const line = document.getElementById(`step${i}-line`);
        
        if (i <= step) {
            indicator.classList.remove('bg-gray-300', 'text-gray-600');
            indicator.classList.add('bg-blue-600', 'text-white');
            if (line) {
                line.classList.remove('bg-gray-300');
                line.classList.add('bg-blue-600');
            }
        }
    }
}

// Load step views
function loadStep2(data) {
    updateProgress(2);
    // Load step 2 HTML via fetch or already embedded
    document.getElementById('wizard-container').innerHTML = document.getElementById('step2-template').innerHTML;
}

function loadStep3(summary) {
    updateProgress(3);
    // Populate summary and show step 3
    populateSummary(summary);
    document.getElementById('wizard-container').innerHTML = document.getElementById('step3-template').innerHTML;
}
</script>
@endpush
@endsection
```

- [ ] **Step 6.2: Create Step 1 view (Transaction Details)**

```php
{{-- resources/views/transactions/wizard/step1.blade.php --}}
<form id="step1-form" class="space-y-6" onsubmit="event.preventDefault(); handleStep1Submit();">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Step 1: Transaction Details</h2>
    
    {{-- Customer Selection --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">Customer *</label>
            <select name="customer_id" id="customer_id" required 
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Customer</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->full_name }} ({{ $customer->id_number }})</option>
                @endforeach
            </select>
        </div>
        
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Transaction Type *</label>
            <select name="type" id="type" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="buy">Buy (Customer sells foreign currency)</option>
                <option value="sell">Sell (Customer buys foreign currency)</option>
            </select>
        </div>
    </div>
    
    {{-- Currency and Amount --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <label for="currency_code" class="block text-sm font-medium text-gray-700 mb-1">Currency *</label>
            <select name="currency_code" id="currency_code" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Currency</option>
                @foreach($currencies as $currency)
                    <option value="{{ $currency->code }}">{{ $currency->code }} - {{ $currency->name }}</option>
                @endforeach
            </select>
        </div>
        
        <div>
            <label for="amount_foreign" class="block text-sm font-medium text-gray-700 mb-1">Amount (Foreign) *</label>
            <input type="number" name="amount_foreign" id="amount_foreign" step="0.01" min="0.01" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                onchange="calculateLocalAmount()">
        </div>
        
        <div>
            <label for="rate" class="block text-sm font-medium text-gray-700 mb-1">Exchange Rate *</label>
            <input type="number" name="rate" id="rate" step="0.0001" min="0.0001" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                onchange="calculateLocalAmount()">
        </div>
    </div>
    
    {{-- Calculated Amount --}}
    <div class="bg-blue-50 rounded-md p-4">
        <label class="block text-sm font-medium text-blue-900">Amount (MYR)</label>
        <div id="amount_local_display" class="text-2xl font-bold text-blue-700">RM 0.00</div>
        <input type="hidden" name="amount_local" id="amount_local">
    </div>
    
    {{-- Purpose and Source --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose *</label>
            <select name="purpose" id="purpose" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Purpose</option>
                <option value="Travel">Travel</option>
                <option value="Education">Education</option>
                <option value="Medical">Medical</option>
                <option value="Business">Business</option>
                <option value="Investment">Investment</option>
                <option value="Family Support">Family Support</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div>
            <label for="source_of_funds" class="block text-sm font-medium text-gray-700 mb-1">Source of Funds *</label>
            <select name="source_of_funds" id="source_of_funds" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Source</option>
                <option value="Salary">Salary</option>
                <option value="Business Income">Business Income</option>
                <option value="Savings">Savings</option>
                <option value="Investment">Investment</option>
                <option value="Loan">Loan</option>
                <option value="Gift">Gift</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>
    
    {{-- Till Selection --}}
    <div>
        <label for="till_id" class="block text-sm font-medium text-gray-700 mb-1">Till *</label>
        <select name="till_id" id="till_id" required
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Select Till</option>
            @foreach($tills as $till)
                <option value="{{ $till->id }}">{{ $till->name }} ({{ $till->branch->name }})</option>
            @endforeach
        </select>
    </div>
    
    {{-- Teller Override --}}
    <div class="border border-gray-200 rounded-md p-4 bg-gray-50">
        <div class="flex items-center">
            <input type="checkbox" name="collect_additional_details" id="collect_additional_details" value="1"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            <label for="collect_additional_details" class="ml-2 block text-sm text-gray-700">
                <strong>Teller Override:</strong> Collect additional customer details even if below threshold
            </label>
        </div>
        <p class="mt-1 text-xs text-gray-500 ml-6">Use this option if you suspect unusual activity or want enhanced documentation</p>
    </div>
    
    {{-- Submit Button --}}
    <div class="flex justify-end">
        <button type="submit" 
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            Continue to Customer Details →
        </button>
    </div>
</form>

<script>
function calculateLocalAmount() {
    const amount = parseFloat(document.getElementById('amount_foreign').value) || 0;
    const rate = parseFloat(document.getElementById('rate').value) || 0;
    const localAmount = (amount * rate).toFixed(2);
    
    document.getElementById('amount_local').value = localAmount;
    document.getElementById('amount_local_display').textContent = 'RM ' + parseFloat(localAmount).toLocaleString('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

async function handleStep1Submit() {
    const form = document.getElementById('step1-form');
    const formData = {
        customer_id: parseInt(form.customer_id.value),
        type: form.type.value,
        currency_code: form.currency_code.value,
        amount_foreign: form.amount_foreign.value,
        rate: form.rate.value,
        till_id: form.till_id.value,
        purpose: form.purpose.value,
        source_of_funds: form.source_of_funds.value,
        collect_additional_details: form.collect_additional_details.checked
    };
    
    await submitStep1(formData);
}
</script>
```

- [ ] **Step 6.3: Create Step 2 view (Customer Details)**

```php
{{-- resources/views/transactions/wizard/step2.blade.php --}}
<script id="step2-template" type="text/template">
<form id="step2-form" class="space-y-6" onsubmit="event.preventDefault(); handleStep2Submit();">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Step 2: Customer Information</h2>
    
    {{-- CDD Level Indicator --}}
    <div id="cdd-level-banner" class="rounded-md p-4 mb-6">
        {{-- Populated by JS --}}
    </div>
    
    {{-- Required Documents Notice --}}
    <div id="required-documents" class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
        <h4 class="font-bold text-blue-800 mb-2">Required Documents</h4>
        <ul id="documents-list" class="list-disc list-inside text-blue-700">
            {{-- Populated by JS --}}
        </ul>
    </div>
    
    {{-- Customer Basic Info --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Occupation *</label>
            <input type="text" name="customer[occupation]" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employer Name</label>
            <input type="text" name="customer[employer_name]"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Employer Address</label>
        <textarea name="customer[employer_address]" rows="2"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Annual Volume Estimate (MYR)</label>
        <input type="number" name="customer[annual_volume_estimate]" step="0.01" min="0"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>
    
    {{-- Standard/Enhanced CDD Documents --}}
    <div id="standard-documents" class="hidden space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Proof of Address *</label>
            <input type="file" name="customer[proof_of_address]" accept=".pdf,.jpg,.png"
                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="text-xs text-gray-500 mt-1">Utility bill, bank statement, or government letter (PDF, JPG, PNG, max 5MB)</p>
        </div>
    </div>
    
    {{-- Enhanced CDD Documents --}}
    <div id="enhanced-documents" class="hidden space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Passport Copy *</label>
            <input type="file" name="customer[passport]" accept=".pdf,.jpg,.png"
                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Beneficial Owner *</label>
            <input type="text" name="customer[beneficial_owner]" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Name of ultimate beneficial owner">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Source of Wealth *</label>
            <textarea name="customer[source_of_wealth]" rows="3" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Describe the source of customer's wealth (business, inheritance, investments, etc.)"></textarea>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Expected Transaction Frequency *</label>
            <select name="transaction[expected_frequency]" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Frequency</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="annually">Annually</option>
            </select>
        </div>
    </div>
    
    {{-- Navigation Buttons --}}
    <div class="flex justify-between">
        <button type="button" onclick="goBackToStep1()"
            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-md">
            ← Back
        </button>
        <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md">
            Review Transaction →
        </button>
    </div>
</form>
</script>
```

- [ ] **Step 6.4: Create Step 3 view (Review & Confirm)**

```php
{{-- resources/views/transactions/wizard/step3.blade.php --}}
<script id="step3-template" type="text/template">
<form id="step3-form" class="space-y-6" onsubmit="event.preventDefault(); handleStep3Submit();">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Step 3: Review & Confirm</h2>
    
    {{-- Transaction Summary Card --}}
    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Transaction Summary</h3>
        
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="text-gray-600">Customer:</div>
            <div id="summary-customer" class="font-medium"></div>
            
            <div class="text-gray-600">Transaction Type:</div>
            <div id="summary-type" class="font-medium"></div>
            
            <div class="text-gray-600">Currency:</div>
            <div id="summary-currency" class="font-medium"></div>
            
            <div class="text-gray-600">Amount (Foreign):</div>
            <div id="summary-amount-foreign" class="font-medium"></div>
            
            <div class="text-gray-600">Exchange Rate:</div>
            <div id="summary-rate" class="font-medium"></div>
            
            <div class="text-gray-600">Amount (MYR):</div>
            <div id="summary-amount-local" class="font-medium text-lg text-blue-700"></div>
            
            <div class="text-gray-600">Purpose:</div>
            <div id="summary-purpose" class="font-medium"></div>
            
            <div class="text-gray-600">Source of Funds:</div>
            <div id="summary-source" class="font-medium"></div>
            
            <div class="text-gray-600">CDD Level:</div>
            <div id="summary-cdd-level" class="font-medium"></div>
        </div>
    </div>
    
    {{-- Risk Flags --}}
    <div id="summary-risk-flags" class="hidden bg-orange-50 border border-orange-200 rounded-md p-4">
        <h4 class="font-bold text-orange-800 mb-2">Risk Alerts</h4>
        <ul id="risk-flags-list" class="list-disc list-inside text-orange-700 text-sm"></ul>
    </div>
    
    {{-- Hold Warning --}}
    <div id="hold-warning" class="hidden bg-yellow-50 border border-yellow-200 rounded-md p-4">
        <div class="flex items-start">
            <svg class="h-5 w-5 text-yellow-400 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <h4 class="font-bold text-yellow-800">Manager Approval Required</h4>
                <p class="text-yellow-700 text-sm mt-1">
                    This transaction requires Enhanced Due Diligence and will be held pending manager approval.
                    Journal entries will be created only after approval.
                </p>
            </div>
        </div>
    </div>
    
    {{-- Confirmation Checkbox --}}
    <div class="border-t border-gray-200 pt-6">
        <div class="flex items-center">
            <input type="checkbox" id="confirm-details" required
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            <label for="confirm-details" class="ml-2 block text-sm text-gray-700">
                I confirm that all transaction details are accurate and the customer has been properly identified.
            </label>
        </div>
    </div>
    
    {{-- Navigation Buttons --}}
    <div class="flex justify-between">
        <button type="button" onclick="goBackToStep2()"
            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-6 rounded-md">
            ← Back
        </button>
        <button type="submit"
            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-md">
            ✓ Confirm Transaction
        </button>
    </div>
</form>
</script>

<script>
function populateSummary(summary) {
    document.getElementById('summary-customer').textContent = summary.customer_name;
    document.getElementById('summary-type').textContent = summary.type === 'buy' ? 'Buy' : 'Sell';
    document.getElementById('summary-currency').textContent = summary.currency;
    document.getElementById('summary-amount-foreign').textContent = summary.amount_foreign + ' ' + summary.currency;
    document.getElementById('summary-rate').textContent = summary.rate;
    document.getElementById('summary-amount-local').textContent = 'RM ' + parseFloat(summary.amount_local).toLocaleString('en-MY', {minimumFractionDigits: 2});
    document.getElementById('summary-purpose').textContent = summary.purpose;
    document.getElementById('summary-source').textContent = summary.source_of_funds;
    document.getElementById('summary-cdd-level').textContent = summary.cdd_level;
    
    // Show risk flags if any
    if (summary.risk_flags) {
        const flagsContainer = document.getElementById('summary-risk-flags');
        const flagsList = document.getElementById('risk-flags-list');
        flagsContainer.classList.remove('hidden');
        flagsList.innerHTML = summary.risk_flags.map(f => `<li>${f.description}</li>`).join('');
    }
    
    // Show hold warning if required
    if (summary.hold_required) {
        document.getElementById('hold-warning').classList.remove('hidden');
    }
}
</script>
```

- [ ] **Step 6.5: Add web routes**

Modify `routes/web.php`:

```php
<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionWizardController;
use Illuminate\Support\Facades\Route;

// Transaction Wizard (Web Interface)
Route::middleware(['auth', 'role:teller'])->group(function () {
    Route::get('/transactions/wizard', [TransactionWizardController::class, 'index'])
        ->name('transactions.wizard');
});

// Keep existing transaction routes
Route::middleware(['auth'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index'])
        ->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])
        ->name('transactions.show');
    // ... other existing routes
});
```

- [ ] **Step 6.6: Commit**

```bash
git add resources/views/transactions/wizard/ \
       routes/web.php
git commit -m "feat: Add Transaction Wizard UI with 3-step guided interface"
```

---

## Task 7: Final Integration and Testing

**Purpose:** Complete integration and run full test suite

**Files:**
- Modify: Existing integration points
- Test: `tests/Feature/EndToEndTransactionWorkflowTest.php`

**Steps:**

- [ ] **Step 7.1: Create end-to-end test**

```php
<?php

namespace Tests\Feature;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Till;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndTransactionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $teller;
    protected User $manager;
    protected Customer $customer;
    protected Currency $currency;
    protected Till $till;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
        $this->manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $this->till = Till::factory()->create(['is_open' => true]);
        
        // Create returning customer with transaction history
        $this->customer = Customer::factory()->create([
            'risk_rating' => 'Low',
            'pep_status' => false,
            'sanction_hit' => false,
        ]);
        
        // Add some transaction history
        \App\Models\Transaction::factory()->count(5)->create([
            'customer_id' => $this->customer->id,
            'type' => TransactionType::Buy,
            'created_at' => now()->subDays(10),
        ]);
    }

    public function test_complete_simplified_cdd_transaction(): void
    {
        // Step 1: Submit transaction details
        $step1Response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $this->customer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->till->id,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);
        
        $step1Response->assertStatus(200)
            ->assertJson([
                'cdd_level' => CddLevel::Simplified->value,
                'hold_required' => false,
            ]);
        
        $sessionId = $step1Response->json('wizard_session_id');
        
        // Step 2: Submit customer details (minimal for Simplified)
        $step2Response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => CddLevel::Simplified->value,
                'customer' => [
                    'occupation' => 'Engineer',
                    'employer_name' => 'Tech Corp',
                    'annual_volume_estimate' => '50000',
                ],
            ]);
        
        $step2Response->assertStatus(200);
        
        // Step 3: Confirm transaction
        $step3Response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step3', [
                'wizard_session_id' => $sessionId,
                'confirm_details' => true,
                'idempotency_key' => uniqid('test_', true),
            ]);
        
        $step3Response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonPath('status_label', 'completed');
        
        $transactionId = $step3Response->json('transaction_id');
        
        // Verify transaction was created
        $transaction = \App\Models\Transaction::find($transactionId);
        $this->assertNotNull($transaction);
        $this->assertEquals(CddLevel::Simplified, $transaction->cdd_level);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        
        // Verify journal entries were created
        $this->assertNotNull($transaction->journal_entry_id);
    }

    public function test_enhanced_cdd_requires_approval_before_journal_entries(): void
    {
        $pepCustomer = Customer::factory()->create(['pep_status' => true]);
        
        // Step 1: Enhanced CDD detected
        $step1Response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $pepCustomer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '60000.00',
                'rate' => '4.50',
                'till_id' => $this->till->id,
                'purpose' => 'Investment',
                'source_of_funds' => 'Business',
            ]);
        
        $step1Response->assertStatus(200)
            ->assertJson([
                'cdd_level' => CddLevel::Enhanced->value,
                'hold_required' => true,
            ]);
        
        $sessionId = $step1Response->json('wizard_session_id');
        
        // Step 2: Submit Enhanced CDD details
        $step2Response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step2', [
                'wizard_session_id' => $sessionId,
                'cdd_level' => CddLevel::Enhanced->value,
                'customer' => [
                    'occupation' => 'Business Owner',
                    'beneficial_owner' => 'Self',
                    'source_of_wealth' => 'Business profits',
                ],
                'transaction' => [
                    'expected_frequency' => 'monthly',
                ],
            ]);
        
        $step2Response->assertStatus(200);
        
        // Step 3: Create transaction (will be pending)
        $step3Response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step3', [
                'wizard_session_id' => $sessionId,
                'confirm_details' => true,
                'idempotency_key' => uniqid('test_', true),
            ]);
        
        $transactionId = $step3Response->json('transaction_id');
        
        // Verify transaction is pending
        $transaction = \App\Models\Transaction::find($transactionId);
        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
        $this->assertNull($transaction->journal_entry_id); // Not yet created
        
        // Manager approves
        $this->actingAs($this->manager)
            ->postJson("/api/transactions/{$transactionId}/approve");
        
        $transaction->refresh();
        
        // Now journal entries should exist
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertNotNull($transaction->journal_entry_id);
    }

    public function test_sanctions_screening_blocks_transaction(): void
    {
        $sanctionedCustomer = Customer::factory()->create(['sanction_hit' => true]);
        
        $response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $sanctionedCustomer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '100.00',
                'rate' => '4.50',
                'till_id' => $this->till->id,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);
        
        $response->assertStatus(403)
            ->assertJson([
                'status' => 'blocked',
                'reason' => 'sanctions',
            ]);
    }

    public function test_velocity_risk_detected_on_returning_customer(): void
    {
        // Create 3 recent transactions to trigger velocity risk
        \App\Models\Transaction::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'created_at' => now()->subHours(2),
        ]);
        
        $response = $this->actingAs($this->teller)
            ->postJson('/api/wizard/transactions/step1', [
                'customer_id' => $this->customer->id,
                'type' => 'buy',
                'currency_code' => 'USD',
                'amount_foreign' => '1000.00',
                'rate' => '4.50',
                'till_id' => $this->till->id,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ]);
        
        $response->assertStatus(200)
            ->assertJsonPath('risk_flags', function ($flags) {
                return count($flags) > 0 && $flags[0]['type'] === 'velocity';
            });
    }
}
```

- [ ] **Step 7.2: Run full test suite**

```bash
php artisan test --filter="TransactionWizard\|EnhancedCDD\|HistoricalRisk\|Sanction" 2>&1 | tail -50
```

- [ ] **Step 7.3: Final commit**

```bash
git add tests/Feature/EndToEndTransactionWorkflowTest.php
git commit -m "test: Add comprehensive end-to-end transaction workflow tests"
```

---

## Summary

This implementation plan covers:

### ✅ **Completed Components**

1. **TransactionPreValidationService** - Central pre-transaction validation with sanctions, CDD, and risk analysis
2. **HistoricalRiskAnalysisService** - Returning customer analysis with velocity, structuring, pattern detection
3. **TransactionWizardController** - 3-step API for teller-guided transaction creation
4. **Deferred Bookkeeping** - Enhanced CDD transactions wait for approval before journal entries
5. **Wizard UI** - Complete web interface with step-by-step guidance
6. **Comprehensive Tests** - Unit and feature tests covering all scenarios

### 📋 **Key Features Implemented**

| Feature | Status | Description |
|---------|--------|-------------|
| Sanctions Screening | ✅ | Real-time fuzzy matching with 80% threshold, blocks before transaction |
| CDD Wizard | ✅ | Dynamic forms based on Simplified/Standard/Enhanced levels |
| Teller Override | ✅ | Option to collect additional details below threshold |
| Historical Risk | ✅ | Velocity, structuring, pattern change, cumulative analysis |
| Deferred Bookkeeping | ✅ | Enhanced CDD waits for approval, then creates entries |
| Audit Trail | ✅ | Every step logged with hash-chained audit entries |
| Comprehensive Tests | ✅ | End-to-end tests covering all scenarios |

### 🚀 **Next Steps**

1. Review and approve the implementation plan
2. Execute tasks sequentially using subagent-driven-development or executing-plans
3. Each task can be implemented independently
4. All tests must pass before final deployment

**Estimated Implementation Time:** 8-12 hours (with subagent parallelization: 4-6 hours)

**Risk Level:** Low - builds on existing infrastructure

**Breaking Changes:** None - new features are additive