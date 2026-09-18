<?php

namespace App\Http\Controllers;

use App\Enums\CddLevel;
use App\Enums\TransactionType;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Http\Concerns\MapsTransactionExceptionsToFields;
use App\Http\Requests\TransactionWizardStep1Request;
use App\Http\Requests\TransactionWizardStep2Request;
use App\Http\Requests\TransactionWizardStep3Request;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\System\MathService;
use App\Services\System\WizardSessionService;
use App\Services\ThresholdService;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use App\Services\Transaction\ExchangeCalculator;
use App\Services\Transaction\InitialStatusResolver;
use App\Services\Transaction\TransactionApprovalService;
use App\ValueObjects\QuoteConvention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

class TransactionWizardController extends Controller
{
    use Concerns\AuthorizesBranchResource;
    use MapsTransactionExceptionsToFields;

    public function __construct(
        protected TransactionValidationInterface $validationService,
        protected TransactionCreationServiceInterface $creationService,
        protected TransactionApprovalService $approvalService,
        protected WizardSessionService $wizardSessionService,
        protected MathService $mathService,
        protected ExchangeCalculator $exchangeCalculator,
        protected TellerAllocationService $tellerAllocationService,
        protected ThresholdService $thresholdService,
        protected InitialStatusResolver $statusResolver,
        protected LoggerInterface $logger,
    ) {}

    /**
     * Render the transaction wizard page.
     */
    public function index(): Response
    {
        $activeCurrencies = Currency::where('is_active', true)->get(['code', 'name', 'rate_unit', 'rate_inverse']);
        $currencies = $activeCurrencies->pluck('name', 'code');
        $currencyUnits = $activeCurrencies->pluck('rate_unit', 'code');
        $currencyInverses = $activeCurrencies->pluck('rate_inverse', 'code');

        return response()->view('transaction-wizard.index', compact('currencies', 'currencyUnits', 'currencyInverses') + ['idempotencyKey' => Str::uuid()]);
    }

    /**
     * Step 1: Initial transaction data + CDD assessment
     */
    public function step1(TransactionWizardStep1Request $request): JsonResponse
    {
        $validated = $request->validated();
        /** @var Customer|null $customer */
        $customer = Customer::find($validated['customer_id']);

        if (! $customer instanceof Customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        // Branch isolation: refuse to leak existence/risk flags of customers
        // that belong to another branch.
        if ($denied = $this->authorizeAssignedBranch('You are not authorized to create transactions for this customer.')) {
            return $denied;
        }

        // Calculate local amount (single source of truth for the conversion).
        // The entered rate is quoted per the currency's convention
        // (currencies.rate_unit + rate_inverse).
        $amountLocal = $this->exchangeCalculator->calculate(
            TransactionType::from((string) $validated['type']),
            (string) $validated['currency_code'],
            (string) $validated['amount_foreign'],
            (string) $validated['rate'],
            null,
            QuoteConvention::forCode((string) $validated['currency_code'])
        )['amount_local'];

        // Run pre-validation (sanctions, CDD, risk)
        $validationResult = $this->validationService->preValidate(
            $customer,
            $amountLocal,
            $validated['currency_code']
        );

        // Check if blocked
        if ($validationResult->isBlocked()) {
            return response()->json([
                'success' => false,
                'blocked' => true,
                'message' => $validationResult->getBlocks()[0]['message'],
                'reason' => $validationResult->getBlocks()[0]['type'],
            ], 403);
        }

        // Determine CDD level (allow teller override)
        $cddLevel = $validationResult->getCDDLevel();
        if ($request->boolean('collect_additional_details')) {
            $cddLevel = $this->upgradeCDDLevel($cddLevel);
        }

        // Create wizard session
        $sessionId = Str::uuid()->toString();
        $sessionData = [
            'step' => 1,
            'user_id' => auth()->id(),
            'customer_id' => $customer->id,
            'transaction_data' => $validated,
            'amount_local' => $amountLocal,
            'cdd_level' => $cddLevel->value,
            'risk_flags' => $validationResult->getRiskFlags(),
            'hold_required' => $validationResult->isHoldRequired(),
            'created_at' => now(),
        ];

        $this->wizardSessionService->put($sessionId, $sessionData);

        // Prepare required documents list
        $requiredDocuments = $this->getRequiredDocuments($cddLevel);

        return response()->json([
            'success' => true,
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

        $sessionData = $this->getOwnedSession($sessionId);
        if ($sessionData instanceof JsonResponse) {
            return $sessionData;
        }

        // Update session with customer details (UploadedFile objects in
        // customer.* are stored as paths via processDocuments — the raw file
        // objects are not serializable into the wizard session store).
        $sessionData['step'] = 2;
        $sessionData['customer_details'] = Arr::except(
            $validated['customer'] ?? [],
            ['proof_of_address', 'passport']
        );
        $sessionData['transaction_meta'] = $validated['transaction'] ?? [];
        $sessionData['documents'] = $this->processDocuments($request);

        $this->wizardSessionService->put($sessionId, $sessionData);

        // Prepare summary for review
        $summary = $this->prepareTransactionSummary($sessionData);

        return response()->json([
            'success' => true,
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

        $sessionData = $this->getOwnedSession($sessionId);
        if ($sessionData instanceof JsonResponse) {
            return $sessionData;
        }

        // Prepare final transaction data
        $transactionData = array_merge(
            $sessionData['transaction_data'],
            [
                'amount_local' => $sessionData['amount_local'],
                'cdd_level' => $sessionData['cdd_level'],
                'idempotency_key' => $validated['idempotency_key'],
                'source_of_wealth' => $sessionData['customer_details']['source_of_wealth'] ?? null,
            ]
        );

        // The session rate is quoted per the currency's convention
        // (currencies.rate_unit + rate_inverse); the stored transaction
        // keeps the normalized per-unit rate.
        $transactionData['rate'] = QuoteConvention::forCode((string) $transactionData['currency_code'])
            ->toPerUnit((string) $transactionData['rate']);

        try {
            $this->validationService->validateCurrency($transactionData['currency_code']);
            $this->validationService->validateIpAddress(request()->ip());

            $tillBalance = $this->validationService->validateTillBalance(
                $transactionData['till_id'],
                $transactionData['currency_code']
            );

            /** @var Customer $customer */
            $customer = Customer::findOrFail($transactionData['customer_id']);

            // Re-check branch isolation at creation time (fail closed).
            if ($denied = $this->authorizeAssignedBranch('You are not authorized to create transactions for this customer.')) {
                return $denied;
            }

            $amountLocal = (string) $sessionData['amount_local'];

            $this->validationService->validatePepRequirements($customer, $transactionData);

            $user = User::findOrFail(auth()->id());
            $allocation = $this->tellerAllocationService->resolveForTransaction(
                $user,
                [
                    'type' => (string) $transactionData['type'],
                    'currency_code' => (string) $transactionData['currency_code'],
                ],
                $amountLocal
            );

            $holdRequired = (bool) $sessionData['hold_required'];
            $initialStatus = $this->statusResolver->resolve($amountLocal, $holdRequired, $customer->risk_rating);

            $context = new TransactionCreationContext(
                data: $transactionData,
                customer: $customer,
                tillBalance: $tillBalance,
                cddLevel: CddLevel::from($sessionData['cdd_level']),
                holdRequired: $holdRequired,
                status: $initialStatus->status,
                amountLocal: $amountLocal,
                user: $user,
                allocation: $allocation,
                // hold_reason doubles as the compliance-clear gate — only a
                // genuine hold may populate it, not threshold/risk reasons.
                holdReason: $holdRequired ? $initialStatus->holdReason : null,
            );

            $transaction = $this->creationService->create($context, $user->id, request()->ip());

            // Clear wizard session
            $this->wizardSessionService->forget($sessionId);

            return response()->json([
                'success' => true,
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->reference,
                'transaction_status' => $transaction->status->value,
                'message' => $holdRequired
                    ? 'Transaction created and pending approval'
                    : 'Transaction completed successfully',
            ]);

        } catch (TransactionBlockedException $e) {
            return response()->json([
                'success' => false,
                'field' => 'customer_id',
                'message' => 'Transaction blocked due to compliance restrictions. Please contact support.',
            ], 422);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'field' => $this->transactionExceptionField($e),
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Transaction creation failed in wizard', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transaction creation failed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get wizard session status
     */
    public function status(string $sessionId): JsonResponse
    {
        $sessionData = $this->getOwnedSession($sessionId);

        if ($sessionData instanceof JsonResponse) {
            return $sessionData;
        }

        return response()->json([
            'success' => true,
            'status' => 'active',
            'current_step' => $sessionData['step'],
            'expires_at' => now()->addHour()->toIso8601String(),
        ]);
    }

    /**
     * Fetch a wizard session and enforce ownership: a teller may only continue,
     * inspect, or cancel sessions they created. Without this, any teller could
     * drive another teller's in-progress transaction by guessing the session id.
     */
    private function getOwnedSession(string $sessionId): array|JsonResponse
    {
        $sessionData = $this->wizardSessionService->get($sessionId);

        if (! $sessionData) {
            return response()->json([
                'success' => false,
                'status' => 'expired',
                'message' => 'Wizard session expired or invalid',
            ], 404);
        }

        // Sessions created before ownership tracking lack user_id and are
        // intentionally rejected (fail-closed): their 1-hour TTL makes the
        // migration window negligible, and refusing is safer than trusting an
        // unbound session in an AML workflow.
        if ((int) ($sessionData['user_id'] ?? 0) !== (int) auth()->id()) {
            return response()->json([
                'success' => false,
                'status' => 'forbidden',
                'message' => 'You do not own this wizard session',
            ], 403);
        }

        return $sessionData;
    }

    /**
     * Cancel wizard session
     */
    public function cancel(string $sessionId): JsonResponse
    {
        $sessionData = $this->getOwnedSession($sessionId);

        if ($sessionData instanceof JsonResponse) {
            return $sessionData;
        }

        $this->wizardSessionService->forget($sessionId);

        return response()->json([
            'success' => true,
            'status' => 'cancelled',
            'message' => 'Wizard session cancelled',
        ]);
    }

    // Helper methods

    private function upgradeCDDLevel(CddLevel $current): CddLevel
    {
        return match ($current) {
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
        return match ($cddLevel) {
            CddLevel::Simplified => 'Simplified Due Diligence - Basic customer information required',
            CddLevel::Standard => 'Standard Due Diligence - Additional documentation required',
            CddLevel::Enhanced => 'Enhanced Due Diligence - Comprehensive verification required',
        };
    }

    private function processDocuments(Request $request): array
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
        /** @var Customer|null $customer */
        $customer = Customer::find($data['customer_id']);

        return [
            'customer_name' => $customer === null ? 'Unknown' : $customer->full_name,
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
