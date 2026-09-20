<?php

namespace App\Console\Commands;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Services\System\MathService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off integrity audit for the stock-transfer ledger (plan Phase 4 step 4).
 *
 * Every inter-branch transfer routes value through the inter-branch clearing
 * account (2300): dispatch debits it, receipt/completion/cancel-return legs
 * credit it. For any transfer the clearing residual must equal the value of
 * stock still in transit (Σ (quantity − quantity_received) × item rate); for
 * terminal transfers it must be zero. A non-matching residual means a
 * double-debited dispatch, a double-credited receipt, or a missing leg.
 *
 * Also checks the item-level invariant quantity_in_transit = quantity −
 * quantity_received, which raw position writes historically broke.
 *
 * Read-only. Exit code 1 when discrepancies are found so it can gate a
 * staging soak.
 */
class AuditStockTransferIntegrity extends Command
{
    protected $signature = 'transfers:audit-integrity';

    protected $description = 'Detect double-debit/credit and in-transit drift in stock transfers (read-only)';

    public function handle(MathService $math): int
    {
        $issues = 0;

        StockTransfer::with('items')
            ->whereNotIn('status', [StockTransferStatus::Requested->value])
            ->chunkById(200, function ($transfers) use ($math, &$issues) {
                foreach ($transfers as $transfer) {
                    $issues += $this->auditTransfer($transfer, $math);
                }
            });

        if ($issues === 0) {
            $this->info('No stock-transfer integrity issues found.');

            return self::SUCCESS;
        }

        $this->error("{$issues} integrity issue(s) found — investigate before treating position balances as authoritative.");

        return self::FAILURE;
    }

    private function auditTransfer(StockTransfer $transfer, MathService $math): int
    {
        $issues = 0;

        // Expected in-transit quantity per item: only a dispatched, still-open
        // transfer has stock on the wire. Terminal statuses (Completed,
        // Cancelled, Rejected) and never-dispatched transfers hold none.
        $dispatched = $transfer->dispatched_at !== null;
        $active = in_array($transfer->status, [
            StockTransferStatus::InTransit,
            StockTransferStatus::PartiallyReceived,
            StockTransferStatus::Received,
        ], true);

        foreach ($transfer->items as $item) {
            $expectedInTransit = ($dispatched && $active)
                ? $math->subtract((string) $item->quantity, (string) ($item->quantity_received ?? '0'))
                : '0';

            if ($math->compare((string) ($item->quantity_in_transit ?? '0'), $expectedInTransit) !== 0) {
                $this->warn(
                    "Transfer {$transfer->transfer_number} item {$item->id} ({$item->currency_code}): "
                    ."quantity_in_transit={$item->quantity_in_transit} but expected {$expectedInTransit}"
                );
                $issues++;
            }
        }

        // Within-branch transfers post no GL legs — nothing more to check.
        // Prefer the real FKs; fall back to the name snapshot for legacy rows.
        $sameBranch = ($transfer->source_branch_id !== null || $transfer->destination_branch_id !== null)
            ? $transfer->source_branch_id === $transfer->destination_branch_id
            : $transfer->source_branch_name === $transfer->destination_branch_name;

        if ($sameBranch) {
            return $issues;
        }

        // Clearing residual: debits minus credits on account 2300 across all
        // journal entries for this transfer.
        $clearing = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.reference_type', 'StockTransfer')
            ->where('journal_entries.reference_id', $transfer->id)
            ->where('journal_lines.account_code', '2300')
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) AS dr, COALESCE(SUM(journal_lines.credit),0) AS cr')
            ->first();

        $residual = $math->subtract((string) ($clearing->dr ?? '0'), (string) ($clearing->cr ?? '0'));

        // Expected residual = value of stock still on the wire — zero for
        // terminal or never-dispatched transfers.
        $expectedResidual = '0';
        if ($dispatched && $active) {
            foreach ($transfer->items as $item) {
                $inTransit = $math->subtract((string) $item->quantity, (string) ($item->quantity_received ?? '0'));
                $expectedResidual = $math->add(
                    $expectedResidual,
                    $math->multiply($inTransit, (string) ($item->rate ?? '0'))
                );
            }
        }

        if ($math->compare($residual, $expectedResidual) !== 0) {
            $this->warn(
                "Transfer {$transfer->transfer_number} (status {$transfer->status->value}): "
                ."clearing residual {$residual} MYR but in-transit value is {$expectedResidual} MYR "
                .'— possible double-debit/double-credit or missing GL leg'
            );
            $issues++;
        }

        return $issues;
    }
}
