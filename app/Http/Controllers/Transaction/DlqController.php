<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\System\CacheInvalidationService;
use App\Services\Transaction\TransactionRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin-only dead letter queue management.
 *
 * Surfaces transactions that exhausted automatic retries so admins can review
 * the failure and manually re-queue them for recovery.
 */
class DlqController extends Controller
{
    public function __construct(
        protected TransactionRecoveryService $recoveryService,
        protected AuditService $auditService,
        protected CacheInvalidationService $cacheInvalidationService,
    ) {}

    /**
     * Display the dead letter queue.
     *
     * Only admins may view the DLQ; it contains failure metadata that should
     * not be exposed to branch-level staff.
     */
    public function index(): View
    {
        $this->requireAdmin();

        $transactions = Transaction::with(['customer', 'user', 'transactionErrors'])
            ->where('is_dlq', true)
            ->orderBy('updated_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.dlq', compact('transactions'));
    }

    /**
     * Re-queue a DLQ transaction for recovery.
     *
     * Delegates to TransactionRecoveryService::retryFromDLQ, which resets the
     * error state and returns the transaction to PendingApproval for the
     * manual approval flow (no retry job is dispatched).
     */
    public function retry(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->requireAdmin();

        if (! $this->recoveryService->isInDeadLetterQueue($transaction)) {
            return back()->with('error', 'Transaction is not in the dead letter queue.');
        }

        try {
            $retried = $this->recoveryService->retryFromDLQ($transaction);

            // The DLQ count is cached for the header badge and dashboard
            // widget; refresh it now so the count drops immediately.
            if ($retried) {
                $this->cacheInvalidationService->invalidate('dashboard');
            }

            return back()->with(
                $retried ? 'success' : 'error',
                $retried ? 'Transaction moved out of the DLQ and queued for retry.' : 'Could not retry the transaction.'
            );

        } catch (\Exception $e) {
            Log::error('DLQ retry failed', [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            // Keep internal exception details out of the UI; admins can read
            // the logged error.
            return back()->with('error', 'Retry failed. Check the application logs for details.');
        }
    }

    /**
     * Archive (purge) a DLQ transaction.
     *
     * Soft-deletes the transaction so it leaves the DLQ but the row is retained
     * for the regulatory retention period. An audit trail entry records the
     * action, user, and reason.
     */
    public function purge(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->requireAdmin();

        if (! $this->recoveryService->isInDeadLetterQueue($transaction)) {
            return back()->with('error', 'Transaction is not in the dead letter queue.');
        }

        $reason = trim((string) $request->input('reason', ''));

        try {
            $purged = $this->recoveryService->purgeFromDLQ($transaction);

            if ($purged) {
                // The DLQ count is cached for the header badge and dashboard
                // widget; refresh it now so the count drops immediately.
                $this->cacheInvalidationService->invalidate('dashboard');

                $this->auditService->logWithSeverity(
                    'transaction_dlq_purged',
                    [
                        'entity_type' => 'Transaction',
                        'entity_id' => $transaction->id,
                        'user_id' => auth()->id(),
                        // logWithSeverity only persists entity/old/new value
                        // keys; the reason must ride inside new_values.
                        'new_values' => ['reason' => $reason ?: null],
                    ],
                    'WARNING'
                );

                return back()->with('success', 'Transaction archived and removed from the dead letter queue.');
            }

            return back()->with('error', 'Could not purge the transaction.');

        } catch (\Exception $e) {
            Log::error('DLQ purge failed', [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Purge failed. Check the application logs for details.');
        }
    }
}
