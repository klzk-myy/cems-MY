<?php

namespace App\Http\Controllers;

use App\Enums\CddLevel;
use App\Enums\TransactionType;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Concerns\MapsTransactionExceptionsToFields;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Requests\TransactionWizardStep1Request;
use App\Http\Requests\TransactionWizardStep2Request;
use App\Http\Requests\TransactionWizardStep3Request;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Services\System\WizardSessionService;
use App\Services\Transaction\ExchangeCalculator;
use App\Services\Transaction\TransactionCreationService;
use App\Services\Transaction\TransactionValidationService;
use App\ValueObjects\QuoteConvention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TransactionWizardController extends Controller
{
    use ApiResponse;
    use Concerns\AuthorizesBranchResource;
    use HandlesControllerErrors;
    use MapsTransactionExceptionsToFields;

    public function __construct(
        protected TransactionValidationService $validationService,
        protected TransactionCreationService $creationService,
        protected WizardSessionService $wizardSessionService,
        protected ExchangeCalculator $exchangeCalculator,
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
            return $this->notFoundResponse('Customer not found.');
        }

        // Branch isolation: refuse to leak existence/risk flags of customers
        // that belong to another branch.
        $this->authorizeAssignedBranch('You are not authorized to create transactions for this customer.');

        // Calculate local amount (single source of truth for the conversion).
        // The entered rate is quoted per the currency's convention
        // (currencies.rate_unit + rate_inverse).
        $amountMyr = $this->exchangeCalculator->calculate(
            TransactionType::from((string) $validated['type']),
            (string) $validated['currency_code'],
            (string) $validated['quantity'],
            (string) $validated['rate'],
            null,
            QuoteConvention::forCode((string) $validated['currency_code'])
        )['amount_myr'];

        // Run pre-validation (sanctions, CDD, risk)
        $validationResult = $this->validationService->preValidate(
            $customer,
            $amountMyr,
            $validated['currency_code']
        );

        // Check if blocked
        if ($validationResult->isBlocked()) {
            return $this->errorResponse(
                $validationResult->getBlocks()[0]['message'],
                [],
                403,
                ['blocked' => true, 'reason' => $validationResult->getBlocks()[0]['type']]
            );
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
            'amount_myr' => $amountMyr,
            'cdd_level' => $cddLevel->value,
            'risk_flags' => $validationResult->getRiskFlags(),
            'hold_required' => $validationResult->isHoldRequired(),
            'created_at' => now(),
        ];

        $this->wizardSessionService->put($sessionId, $sessionData);

        // Prepare required documents list
        $requiredDocuments = $this->getRequiredDocuments($cddLevel);

        return $this->successResponse([
            'wizard_session_id' => $sessionId,
            'cdd_level' => $cddLevel->value,
            'cdd_description' => $this->getCDDDescription($cddLevel),
            'hold_required' => $validationResult->isHoldRequired(),
            'risk_flags' => $validationResult->getRiskFlags(),
            'required_documents' => $requiredDocuments,
            'customer_is_returning' => $customer->transactions()->exists(),
            'next_step' => 'customer_details',
        ], 'Step 1 complete');
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

        return $this->successResponse([
            'wizard_session_id' => $sessionId,
            'transaction_summary' => $summary,
            'next_step' => 'review_confirm',
        ], 'Step 2 complete');
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
                'amount_myr' => $sessionData['amount_myr'],
                'cdd_level' => $sessionData['cdd_level'],
                'idempotency_key' => $validated['idempotency_key'],
                'source_of_wealth' => $sessionData['customer_details']['source_of_wealth'] ?? null,
            ]
        );

        try {
            // The live session wins over the step-1 snapshot: a teller who
            // reseated mid-wizard books on the drawer they are actually at.
            // With no open session the booking is drawer-less — custody stays
            // at the teller allocation and no till balance is required.
            $sessionCounter = CounterSession::openCounterForUser((int) auth()->id());
            $transactionData['till_id'] = $sessionCounter !== null
                ? $sessionCounter->code
                : ($transactionData['till_id'] ?? null);

            $user = User::findOrFail(auth()->id());

            // Shared orchestration — the same eligibility gate, exchange
            // calculation, compliance gates, allocation and status resolution
            // the web/API paths run inside buildCreationContext(). The step-1
            // CDD tier is passed as a floor so the effective level never
            // downgrades below the tier documents were collected under.
            $context = $this->creationService->buildCreationContext(
                $user,
                $transactionData,
                CddLevel::from($sessionData['cdd_level']),
                request()->ip()
            );

            $holdRequired = $context->holdRequired;

            $transaction = $this->creationService->create($context, $user->id, request()->ip());

            // Clear wizard session
            $this->wizardSessionService->forget($sessionId);

            return $this->successResponse([
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->reference,
                'transaction_status' => $transaction->status->value,
            ], $holdRequired
                ? 'Transaction created and pending approval'
                : 'Transaction completed successfully');

        } catch (TransactionBlockedException $e) {
            return $this->errorResponse(
                'Transaction blocked due to compliance restrictions. Please contact support.',
                [],
                422,
                ['field' => 'customer_id']
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                [],
                $e->getStatusCode(),
                ['field' => $this->transactionExceptionField($e)]
            );
        } catch (\Throwable $e) {
            return $this->handleExceptionApi($e, 'Transaction creation failed in wizard', 'Transaction creation failed. Please try again later.', 500, [
                'session_id' => $sessionId,
            ]);
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

        return $this->successResponse([
            'status' => 'active',
            'current_step' => $sessionData['step'],
            'expires_at' => now()->addHour()->toIso8601String(),
        ], 'Wizard session active');
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
            return $this->errorResponse('Wizard session expired or invalid', [], 404, ['status' => 'expired']);
        }

        // Sessions created before ownership tracking lack user_id and are
        // intentionally rejected (fail-closed): their 1-hour TTL makes the
        // migration window negligible, and refusing is safer than trusting an
        // unbound session in an AML workflow.
        if ((int) ($sessionData['user_id'] ?? 0) !== (int) auth()->id()) {
            throw new PermissionDeniedException('You do not own this wizard session');
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

        return $this->successResponse(['status' => 'cancelled'], 'Wizard session cancelled');
    }

    // Helper methods

    private function upgradeCDDLevel(CddLevel $current): CddLevel
    {
        return match ($current) {
            CddLevel::Simplified, CddLevel::Specific => CddLevel::Standard,
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

        if (in_array($cddLevel, [CddLevel::Specific, CddLevel::Standard, CddLevel::Enhanced], true)) {
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
            CddLevel::Specific => 'Specific Due Diligence - Identity verification and transaction details required',
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
            'quantity' => $data['quantity'],
            'rate' => $data['rate'],
            'amount_myr' => $sessionData['amount_myr'],
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
