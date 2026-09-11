<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * TransactionSteps — Wave A step A4.
 *
 * A4: book a transaction on both surfaces and assert the double-entry
 * invariant: the new journal's debits sum to its credits, and the
 * transaction is recorded.
 */
trait TransactionSteps
{
    /**
     * A4 — book a USD buy on the web surface.
     */
    protected function itBooksTransaction(): int
    {
        $idempotency = 'wave-a-web-'.uniqid();
        $resp = $this->webClient->post('/transactions', [
            'customer_id' => $this->state->customerId,
            'type' => 'Buy',
            'currency_code' => 'USD',
            'amount_foreign' => '3000.00',
            'rate' => '4.50',
            'purpose' => 'Wave A web purchase',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Employer',
            'branch_id' => $this->state->branchId,
            'counter_id' => $this->state->counterId,
            'idempotency_key' => $idempotency,
        ]);

        $this->assertSurfaceStatus($resp, 302, 'A4 web transaction store');

        $txId = $this->state->oracle->scalar(
            'SELECT id FROM transactions WHERE idempotency_key = ?',
            [$idempotency]
        );

        $this->assertNotFalse($txId, 'A4: transaction was not recorded');
        $this->state->webTransactionId = (int) $txId;
        // The cancellation chain (A6) reads transactionId; the API booking
        // (A4b) overrides it when it runs, so the web booking is the fallback.
        $this->state->transactionId = (int) $txId;

        $this->assertJournalBalanced((int) $txId, 'A4 web');

        return (int) $txId;
    }

    /**
     * A4b — book a USD buy on the API surface.
     */
    protected function itBooksTransactionViaApi(): int
    {
        $idempotency = 'wave-a-api-'.uniqid();
        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->post('/transactions', [
            'customer_id' => $this->state->customerId,
            'type' => 'Buy',
            'currency_code' => 'USD',
            'amount_foreign' => '3000.00',
            'rate' => '4.50',
            'purpose' => 'Wave A API purchase',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Employer',
            'till_id' => $this->counterCodeLocal(),
            'idempotency_key' => $idempotency,
        ]);

        $this->assertSurfaceStatus($resp, 201, 'A4b API transaction store');
        $body = json_decode($resp['body'], true);
        $this->assertIsArray($body, 'A4b API transaction store body');

        $txId = $this->state->oracle->scalar(
            'SELECT id FROM transactions WHERE idempotency_key = ?',
            [$idempotency]
        );

        $this->assertNotFalse($txId, 'A4b: transaction was not recorded');
        $this->state->apiTransactionId = (int) $txId;
        $this->state->transactionId = (int) $txId;

        $this->assertJournalBalanced((int) $txId, 'A4b API');

        return (int) $txId;
    }

    /**
     * Assert the transaction's journal entries are double-entry balanced.
     */
    private function assertJournalBalanced(int $txId, string $label): void
    {
        $rows = $this->state->oracle->query(
            "SELECT SUM(debit) AS total_debit, SUM(credit) AS total_credit
             FROM journal_lines
             JOIN journal_entries ON journal_lines.journal_entry_id = journal_entries.id
             WHERE journal_entries.reference_type = 'Transaction' AND journal_entries.reference_id = ?",
            [$txId]
        );

        $this->assertNotEmpty($rows, "{$label}: no journal lines for transaction {$txId}");
        $debit = (float) ($rows[0]['total_debit'] ?? 0);
        $credit = (float) ($rows[0]['total_credit'] ?? 0);

        $this->assertSame(0.0, round($debit - $credit, 2), "{$label}: journal debits != credits");
    }

    private function counterCodeLocal(): string
    {
        $code = $this->state->oracle->scalar('SELECT code FROM counters WHERE id = ?', [$this->state->counterId]);

        return is_string($code) ? $code : 'C001';
    }
}
