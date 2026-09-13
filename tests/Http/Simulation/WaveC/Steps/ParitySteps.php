<?php

namespace Tests\Http\Simulation\WaveC\Steps;

/**
 * ParitySteps — Wave C cross-surface parity.
 *
 * Runs the same booking workflow (open counter → book USD buy → manager
 * approval) once per surface and derives a normalized state snapshot from
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

        $resp = $this->webClient->post('/counters/'.$this->counterCode().'/open', [
            'opening_floats' => [
                ['currency_id' => 'USD', 'amount' => 100000],
                ['currency_id' => 'EUR', 'amount' => 100000],
                ['currency_id' => 'GBP', 'amount' => 100000],
                ['currency_id' => 'MYR', 'amount' => 100000],
            ],
            'notes' => 'Wave C web open',
        ]);
        $this->assertSurfaceStatus($resp, 302, 'Wave C web counter open');

        $key = 'wave-c-web-'.uniqid();
        $resp = $this->webClient->post('/transactions', [
            'customer_id' => $this->state->customerId,
            'type' => 'Buy',
            'currency_code' => 'USD',
            'amount_foreign' => self::PARITY_AMOUNT,
            'rate' => self::PARITY_RATE,
            'purpose' => 'Wave C parity booking',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Employer',
            'branch_id' => $this->state->branchId,
            'counter_id' => $this->state->counterId,
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
     * state. Preconditions (counter close then API open) are plumbing, not
     * part of the proven surface behaviour.
     *
     * @return array<string, mixed>
     */
    protected function runBookingWorkflowOnApi(): array
    {
        // Close the web-run session so the API can open the counter. The web
        // run's booking moved USD, so closing floats must mirror the till's
        // *current* balances (via the read-only oracle) to keep the variance
        // at zero — otherwise the close needs supervisor escalation.
        $closingFloats = [];
        foreach (['USD', 'EUR', 'GBP', 'MYR'] as $currency) {
            // Mirror CounterService::closeSession's expected balance formula:
            // opening + buy_total_foreign - sell_total_foreign.
            $row = $this->state->oracle->query(
                'SELECT opening_balance, buy_total_foreign, sell_total_foreign
                 FROM till_balances WHERE till_id = ? AND currency_code = ? ORDER BY id DESC LIMIT 1',
                [$this->counterCode(), $currency]
            );
            $balance = $row === []
                ? 100000.0
                : (float) $row[0]['opening_balance']
                    + (float) $row[0]['buy_total_foreign']
                    - (float) $row[0]['sell_total_foreign'];
            $closingFloats[] = ['currency_id' => $currency, 'amount' => $balance];
        }

        $this->asWebUser('sim_manager', function () use ($closingFloats): void {
            $resp = $this->webClient->post('/counters/'.$this->counterCode().'/close', [
                'closing_floats' => $closingFloats,
            ]);
            $this->assertSurfaceStatus($resp, 302, 'Wave C API precondition: web close');

            $open = (int) $this->state->oracle->scalar(
                "SELECT COUNT(*) FROM counter_sessions WHERE status = 'open'"
            );
            $this->assertSame(0, $open, 'Wave C API precondition: no session may remain open');
        }, 'sim_manager');

        $before = $this->positionSnapshot();

        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->post('/counters/'.$this->state->counterId.'/opening-request', [
            'requested_floats' => ['USD' => 100000, 'EUR' => 100000, 'GBP' => 100000, 'MYR' => 100000],
        ]);
        $this->assertSurfaceStatus($resp, 200, 'Wave C API opening-request');

        $manager = $this->newApiClient($this->tokenFor('manager'));
        $resp = $manager->post('/counters/'.$this->state->counterId.'/approve-and-open', [
            'teller_id' => $this->state->tellerId,
            'approved_floats' => ['USD' => 100000, 'EUR' => 100000, 'GBP' => 100000, 'MYR' => 100000],
            'daily_limits' => ['USD' => 500000, 'EUR' => 500000, 'GBP' => 500000, 'MYR' => 500000],
        ]);
        $this->assertSurfaceStatus($resp, 200, 'Wave C API approve-and-open');

        $key = 'wave-c-api-'.uniqid();
        $teller = $this->newApiClient($this->tokenFor('teller'));
        $resp = $teller->post('/transactions', [
            'customer_id' => $this->state->customerId,
            'type' => 'Buy',
            'currency_code' => 'USD',
            'amount_foreign' => self::PARITY_AMOUNT,
            'rate' => self::PARITY_RATE,
            'purpose' => 'Wave C parity booking',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Employer',
            'till_id' => $this->counterCode(),
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
            'SELECT type, currency_code, amount_foreign, rate, amount_local, status, customer_id, branch_id
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
            "SELECT currency_code, quantity FROM currency_positions WHERE branch_id = 'HQ'"
        );

        $snapshot = [];
        foreach ($rows as $row) {
            $snapshot[(string) $row['currency_code']] = (float) $row['quantity'];
        }

        return $snapshot;
    }
}
