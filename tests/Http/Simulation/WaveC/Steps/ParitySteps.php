<?php

namespace Tests\Http\Simulation\WaveC\Steps;

/**
 * ParitySteps — Wave C cross-surface parity.
 *
 * Runs the same booking workflow (book USD buy → compliance approval)
 * once per surface — drawerless, no counter session — and derives a
 * normalized state snapshot from
 * the oracle for each run. Identifiers, timestamps, and idempotency keys are
 * stripped, leaving only the semantic truth both surfaces must write
 * identically.
 */
trait ParitySteps
{
    /** Payload identical on both surfaces (API substitutes till_id for ids). */
    private const PARITY_AMOUNT = '3000.00';

    private const PARITY_RATE = '4.50';

    /**
     * Run the workflow on the web surface and return its normalized state.
     *
     * @return array<string, mixed>
     */
    protected function runBookingWorkflowOnWeb(): array
    {
        $this->webClient->login('sim_teller', 'Test@1234');

        $before = $this->positionSnapshot();

        // Drawerless custody: no counter session is opened on either surface —
        // booking settles against the teller allocation, so parity compares
        // the identical drawerless path on both surfaces.
        $key = 'wave-c-web-'.uniqid();
        $resp = $this->webClient->post('/transactions', [
            'customer_id' => $this->state->customerId,
            'type' => 'Buy',
            'currency_code' => 'USD',
            'quantity' => self::PARITY_AMOUNT,
            'rate' => self::PARITY_RATE,
            'purpose' => 'Wave C parity booking',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Employer',
            'branch_id' => $this->state->branchId,
            'idempotency_key' => $key,
        ]);
        $this->assertSurfaceStatus($resp, 302, 'Wave C web booking');

        $txId = (int) $this->state->oracle->scalar(
            'SELECT id FROM transactions WHERE idempotency_key = ?', [$key]
        );
        $this->assertGreaterThan(0, $txId, 'Wave C web booking was not recorded');

        $this->asWebUser('sim_compliance', function () use ($txId): void {
            $resp = $this->webRequester('POST', '/transactions/'.$txId.'/approve', []);
            $this->assertSurfaceStatus($resp, 302, 'Wave C web approve');
        }, 'sim_compliance');

        return $this->deriveState($txId, $before, $this->positionSnapshot());
    }

    /**
     * Run the same workflow on the API surface and return its normalized
     * state.
     *
     * @return array<string, mixed>
     */
    protected function runBookingWorkflowOnApi(): array
    {
        $before = $this->positionSnapshot();

        $key = 'wave-c-api-'.uniqid();
        $teller = $this->newApiClient($this->tokenFor('teller'));
        $resp = $teller->post('/transactions', [
            'customer_id' => $this->state->customerId,
            'type' => 'Buy',
            'currency_code' => 'USD',
            'quantity' => self::PARITY_AMOUNT,
            'rate' => self::PARITY_RATE,
            'purpose' => 'Wave C parity booking',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Employer',
            'idempotency_key' => $key,
        ]);
        $this->assertSurfaceStatus($resp, 201, 'Wave C API booking');

        $txId = (int) $this->state->oracle->scalar(
            'SELECT id FROM transactions WHERE idempotency_key = ?', [$key]
        );
        $this->assertGreaterThan(0, $txId, 'Wave C API booking was not recorded');

        $compliance = $this->newApiClient($this->tokenFor('compliance'));
        $resp = $compliance->post('/transactions/'.$txId.'/approve', []);
        $this->assertSurfaceStatus($resp, 200, 'Wave C API approve');

        return $this->deriveState($txId, $before, $this->positionSnapshot());
    }

    /**
     * Derive the normalized state a booking workflow leaves behind: the
     * transaction's semantic columns, the journal line multiset it produced,
     * and the currency position delta. Anything run-specific (ids, keys,
     * timestamps) is deliberately excluded.
     *
     * @param  array<string, float>  $positionsBefore
     * @param  array<string, float>  $positionsAfter
     * @return array<string, mixed>
     */
    private function deriveState(int $txId, array $positionsBefore, array $positionsAfter): array
    {
        $tx = $this->state->oracle->query(
            'SELECT type, currency_code, quantity, rate, amount_myr, status, customer_id, branch_id
             FROM transactions WHERE id = ?',
            [$txId]
        );
        $this->assertCount(1, $tx, "Wave C: transaction {$txId} missing");

        $transaction = [];
        foreach ($tx[0] as $column => $value) {
            $transaction[$column] = is_numeric($value)
                ? number_format((float) $value, 4, '.', '')
                : $value;
        }

        $lines = $this->state->oracle->query(
            "SELECT journal_lines.account_code, journal_lines.debit, journal_lines.credit
             FROM journal_lines
             JOIN journal_entries ON journal_lines.journal_entry_id = journal_entries.id
             WHERE journal_entries.reference_type = 'Transaction' AND journal_entries.reference_id = ?",
            [$txId]
        );
        $this->assertNotEmpty($lines, "Wave C: transaction {$txId} wrote no journal lines");

        $journal = array_map(fn (array $line): string => implode('|', [
            (string) $line['account_code'],
            number_format((float) $line['debit'], 4, '.', ''),
            number_format((float) $line['credit'], 4, '.', ''),
        ]), $lines);
        sort($journal);

        $delta = [];
        foreach (array_keys($positionsBefore + $positionsAfter) as $currency) {
            $delta[$currency] = number_format(
                ($positionsAfter[$currency] ?? 0.0) - ($positionsBefore[$currency] ?? 0.0),
                4, '.', ''
            );
        }

        return [
            'transaction' => $transaction,
            'journal' => $journal,
            'position_delta' => $delta,
        ];
    }

    /**
     * Currency position quantities keyed by currency code.
     *
     * @return array<string, float>
     */
    private function positionSnapshot(): array
    {
        $rows = $this->state->oracle->query(
            'SELECT currency_code, quantity FROM currency_positions WHERE branch_id IS NULL'
        );

        $snapshot = [];
        foreach ($rows as $row) {
            $snapshot[(string) $row['currency_code']] = (float) $row['quantity'];
        }

        return $snapshot;
    }
}
