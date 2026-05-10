<?php

namespace App\Http\Controllers\Transaction;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use App\Services\ThresholdService;
use App\Services\TransactionApprovalService;
use App\Services\TransactionMonitoringService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionApprovalController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected TransactionApprovalService $approvalService,
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
        protected TransactionMonitoringService $monitoringService,
        protected MathService $mathService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected ThresholdService $thresholdService
    ) {}

    /**
     * Approve pending transaction
     *
     * This method delegates to TransactionService::approveTransaction() which handles:
     * - Status transition from Pending to Completed
     * - Position and till balance updates
     * - Double-entry accounting journal entries
     * - AML/Compliance monitoring before approval
     * - Audit logging
     */
    public function approve(Request $request, Transaction $transaction)
    {
        $this->requireManagerOrAdmin();

        // Enforce branch-based authorization: managers can only approve transactions within their own branch
        $user = auth()->user();
        if (! $user->isAdmin() && $transaction->branch_id !== $user->branch_id) {
            abort(403, 'You can only approve transactions for your own branch.');
        }

        try {
            $this->approvalService->validateApprovalEligibility($transaction, auth()->id());

            $result = $this->approvalService->approve(
                $transaction,
                auth()->id(),
                $request->ip()
            );

            if (! $result['success']) {
                return back()->with('error', $result['message']);
            }

            return redirect()->route('transactions.show', $transaction)
                ->with('success', $result['message']);

        } catch (SelfApprovalException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Transaction approval failed', [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Approval failed due to a system error. Please contact support.');
        }
    }

    /**
     * Show confirmation page for large transactions (>= RM 50,000)
     */
    public function showConfirm(Transaction $transaction)
    {
        // Check if transaction requires confirmation (>= RM 50,000)
        if (! $this->requiresConfirmation($transaction)) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'This transaction does not require confirmation.');
        }

        // Check if there's already a pending confirmation
        $confirmation = TransactionConfirmation::where('transaction_id', $transaction->id)
            ->whereIn('status', [TransactionConfirmationStatus::Pending->value, TransactionConfirmationStatus::Confirmed->value])
            ->first();

        if (! $confirmation) {
            // Create a new confirmation request
            $confirmationToken = bin2hex(random_bytes(32));
            $confirmation = TransactionConfirmation::create([
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'status' => TransactionConfirmationStatus::Pending->value,
                'confirmation_token' => $confirmationToken,
                'expires_at' => now()->addMinutes(30),
            ]);

            $this->auditService->logWithSeverity('confirmation_requested', [
                'user_id' => auth()->id(),
                'entity_type' => 'Transaction',
                'entity_id' => $transaction->id,
                'new_values' => [
                    'confirmation_id' => $confirmation->id,
                    'amount_local' => $transaction->amount_local,
                ],
            ], 'INFO');
        }

        $transaction->load(['customer', 'user']);

        return view('transactions.confirm', compact('transaction', 'confirmation'));
    }

    /**
     * Process transaction confirmation (manager approves large transaction)
     */
    public function confirm(Request $request, Transaction $transaction)
    {
        $this->requireManagerOrAdmin();

        if (! $this->requiresConfirmation($transaction)) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'This transaction does not require confirmation.');
        }

        $confirmation = TransactionConfirmation::where('transaction_id', $transaction->id)
            ->where('status', TransactionConfirmationStatus::Pending->value)
            ->first();

        if (! $confirmation) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'No pending confirmation found.');
        }

        // Prevent self-confirmation (segregation of duties - AML/CFT compliance)
        if ($transaction->user_id === auth()->id()) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'You cannot confirm your own transaction. Segregation of duties requires a different approver.');
        }

        if ($confirmation->isExpired()) {
            $confirmation->markExpired();

            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'Confirmation has expired. Please request a new confirmation.');
        }

        $validated = $request->validate([
            'confirmation_action' => 'required|in:confirm,reject',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            if ($validated['confirmation_action'] === 'confirm') {
                $confirmation->markConfirmed(auth()->id(), $validated['notes'] ?? null);

                // Set transaction to PendingApproval and create approval task
                // (Legacy: this was for Pending/OnHold transactions; now all go directly to PendingApproval)
                $updated = Transaction::where('id', $transaction->id)
                    ->where('status', TransactionStatus::PendingApproval)
                    ->update([
                        'status' => TransactionStatus::PendingApproval,
                    ]);

                if (! $updated) {
                    DB::rollBack();

                    return back()->with('error', 'Transaction could not be updated. Status may have changed.');
                }

                $transaction->refresh();

                $this->auditService->logWithSeverity('transaction_confirmed', [
                    'user_id' => auth()->id(),
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'confirmation_id' => $confirmation->id,
                        'confirmed_by' => auth()->id(),
                    ],
                ], 'INFO');

                DB::commit();

                return redirect()->route('transactions.show', $transaction)
                    ->with('success', 'Transaction confirmed and pending final approval.');

            } else {
                // Reject the transaction
                $confirmation->markRejected(auth()->id(), $validated['notes'] ?? null);

                $transaction->update([
                    'status' => TransactionStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                    'cancellation_reason' => 'Rejected during confirmation: '.($validated['notes'] ?? 'No reason provided'),
                ]);

                $this->auditService->logWithSeverity('transaction_rejected', [
                    'user_id' => auth()->id(),
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'confirmation_id' => $confirmation->id,
                        'rejected_by' => auth()->id(),
                        'reason' => $validated['notes'] ?? 'No reason provided',
                    ],
                ], 'WARNING');

                DB::commit();

                return redirect()->route('transactions.show', $transaction)
                    ->with('warning', 'Transaction has been rejected.');
            }

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Confirmation failed: '.$e->getMessage());
        }
    }

    /**
     * Check if transaction requires manager confirmation (>= RM 50,000)
     */
    protected function requiresConfirmation(Transaction $transaction): bool
    {
        $threshold = $this->thresholdService->getStrThreshold();

        return $this->mathService->compare($transaction->amount_local, $threshold) >= 0;
    }
}
