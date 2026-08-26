<?php

namespace App\Http\Controllers;

use App\Enums\CddLevel;
use App\Enums\UserRole;
use App\Http\Concerns\DeterminesTransactionStatus;
use App\Http\Requests\TransactionWizardStep1Request;
use App\Http\Requests\TransactionWizardStep2Request;
use App\Http\Requests\TransactionWizardStep3Request;
use App\Models\Customer;
use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\System\MathService;
use App\Services\System\WizardSessionService;
use App\Services\ThresholdService;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use App\Services\Transaction\TransactionApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class TransactionWizardController extends Controller
{
    use DeterminesTransactionStatus;

    public function __construct(
        protected TransactionValidationInterface $validationService,
        protected TransactionCreationServiceInterface $creationService,
        protected TransactionApprovalService $approvalService,
        protected WizardSessionService $wizardSessionService,
        protected MathService $mathService,
        protected TellerAllocationService $tellerAllocationService,
        protected ThresholdService $thresholdService,
        protected LoggerInterface $logger,
    ) {}

    /**
     * Render the transaction wizard page.
     */
    public function index(): Response
    {
        return response()->view('transaction-wizard.index');
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
                'status' => 'error',
                'message' => 'Customer not found.',
            ], 404);
        }

        // Branch isolation: refuse to leak existence/risk flags of customers
        // that belong to another branch.
        if ($denied = $this->denyCrossBranchCustomer($customer)) {
            return $denied;
        }

        // Calculate local amount
        $amountLocal = $this->mathService->multiply($validated['amount_foreign'], $validated['rate']);

        // Run pre-validation (sanctions, CDD, risk)
        $validationResult = $this->validationService->preValidate(
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

        $sessionData = $this->getOwnedSession($sessionId);
        if ($sessionData instanceof JsonResponse) {
            return $sessionData;
        }

        // Update session with customer details
        $sessionData['step'] = 2;
        $sessionData['customer_details'] = $validated['customer'] ?? [];
        $sessionData['transaction_meta'] = $validated['transaction'] ?? [];
        $sessionData['documents'] = $this->processDocuments($request);

        $this->wizardSessionService->put($sessionId, $sessionData);

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

        try {
            $this->validationService->validateCurrency($transactionData['currency_code']);
            $this->validationService->validateIpAddress(request()->ip());

            $tillBalance = $this->validationService->validateTillBalance(
                $transactionData['till_id'],
                $transactionData['currency_code']
            );

            /** @var Customer $customer */
            /** @var Customer $customer */
            $customer = Customer::findOrFail($transactionData['customer_id']);

            // Re-check branch isolation at creation time (fail closed).
            if ($denied = $this->denyCrossBranchCustomer($customer)) {
                return $denied;
            }

            $amountLocal = (string) $sessionData['amount_local'];

            $this->validationService->validatePepRequirements($customer, $transactionData);

            $user = User::findOrFail(auth()->id());
            $allocation = $this->determineTellerAllocation(
                $user,
                [
                    'type' => (string) $transactionData['type'],
                    'currency_code' => (string) $transactionData['currency_code'],
                ],
                $amountLocal
            );

            $holdRequired = (bool) $sessionData['hold_required'];
            $status = $this->determineInitialStatus($amountLocal, $holdRequired);

            $context = new TransactionCreationContext(
                data: $transactionData,
                customer: $customer,
                tillBalance: $tillBalance,
                cddLevel: CddLevel::from($sessionData['cdd_level']),
                holdRequired: $holdRequired,
                status: $status,
                amountLocal: $amountLocal,
                user: $user,
                allocation: $allocation,
                holdReason: $holdRequired ? 'Compliance hold' : null,
            );

            $transaction = $this->creationService->create($context, $user->id, request()->ip());

            // Clear wizard session
            $this->wizardSessionService->forget($sessionId);

            return response()->json([
                'status' => 'success',
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->reference,
                'transaction_status' => $transaction->status->value,
                'message' => $holdRequired
                    ? 'Transaction created and pending approval'
                    : 'Transaction completed successfully',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Transaction creation failed in wizard', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
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
            'status' => 'cancelled',
            'message' => 'Wizard session cancelled',
        ]);
    }

    // Helper methods

    /**
     * Enforce customer branch isolation for transaction creation.
     *
     * Applies the same rule as CustomerPolicy::view: admins may serve any
     * customer; everybody else may only serve customers whose transaction
     * history includes their own branch. Customers without any history
     * (walk-ins) are not bound to a branch yet, so their first transaction
     * is allowed anywhere — otherwise no teller could ever serve a new
     * customer. Users without a branch assignment fail closed.
     */
    private function denyCrossBranchCustomer(Customer $customer): ?JsonResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->role === UserRole::Admin) {
            return null;
        }

        if ($user->branch_id === null) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not authorized to create transactions for this customer.',
            ], 403);
        }

        $knownBranchIds = $customer->transactions()->distinct()->pluck('branch_id');

        if ($knownBranchIds->isNotEmpty()
            && ! $knownBranchIds->contains(fn ($branchId) => (int) $branchId === (int) $user->branch_id)
        ) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not authorized to create transactions for this customer.',
            ], 403);
        }

        return null;
    }

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
