<?php

namespace App\Http\Controllers;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransactionWizardController extends Controller
{
    public function __construct(
        protected TransactionValidationInterface $validationService,
        protected TransactionCreationServiceInterface $creationService,
        protected TransactionApprovalService $approvalService,
        protected WizardSessionService $wizardSessionService,
        protected MathService $mathService
    ) {}

    /**
     * Step 1: Initial transaction data + CDD assessment
     */
    public function step1(TransactionWizardStep1Request $request): JsonResponse
    {
        $validated = $request->validated();
        $customer = Customer::find($validated['customer_id']);

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

        $sessionData = $this->wizardSessionService->get($sessionId);
        if (! $sessionData) {
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

        $sessionData = $this->wizardSessionService->get($sessionId);
        if (! $sessionData) {
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

            $customer = Customer::findOrFail($transactionData['customer_id']);
            $amountLocal = (string) $sessionData['amount_local'];

            $this->validationService->validatePepRequirements($customer, $transactionData);

            $user = User::findOrFail(auth()->id());
            $allocation = $this->determineTellerAllocation($user, $transactionData, $amountLocal);

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
                'transaction_number' => $transaction->transaction_number,
                'transaction_status' => $transaction->status->value,
                'message' => $holdRequired
                    ? 'Transaction created and pending approval'
                    : 'Transaction completed successfully',
            ]);

        } catch (\Exception $e) {
            app('log')->error('Transaction creation failed in wizard', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Transaction creation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get wizard session status
     */
    public function status(string $sessionId): JsonResponse
    {
        $sessionData = $this->wizardSessionService->get($sessionId);

        if (! $sessionData) {
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
        $this->wizardSessionService->forget($sessionId);

        return response()->json([
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
        $customer = Customer::find($data['customer_id']);

        return [
            'customer_name' => $customer?->full_name ?? 'Unknown',
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

    private function determineTellerAllocation(User $user, array $data, string $amountLocal): ?Model
    {
        if (! $user->isTeller()) {
            return null;
        }

        $service = app(TellerAllocationService::class);

        if ($data['type'] === TransactionType::Buy->value) {
            $result = $service->validateTransaction($user, $data['currency_code'], $amountLocal, true);

            if (! $result->valid) {
                throw new \InvalidArgumentException($result->reason);
            }

            return $result->allocation;
        }

        return $service->getActiveAllocation($user, $data['currency_code']);
    }

    private function determineInitialStatus(string $amountLocal, bool $holdRequired): TransactionStatus
    {
        if ($holdRequired
            || $this->mathService->compare($amountLocal, app(ThresholdService::class)->getAutoApproveThreshold()) >= 0) {
            return TransactionStatus::PendingApproval;
        }

        return TransactionStatus::Completed;
    }
}
