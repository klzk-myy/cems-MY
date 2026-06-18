<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\SanctionEntry;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class EagerLoadingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function getDefaultWith(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $property = $reflection->getProperty('with');
        $property->setAccessible(true);

        return $property->getValue(new $modelClass);
    }

    public function test_customer_model_does_not_default_eager_load(): void
    {
        $this->assertEmpty($this->getDefaultWith(Customer::class), 'Customer should not auto-eager-load relationships');
    }

    public function test_transaction_model_does_not_default_eager_load(): void
    {
        $this->assertEmpty($this->getDefaultWith(Transaction::class), 'Transaction should not auto-eager-load relationships');
    }

    public function test_journal_entry_model_does_not_default_eager_load(): void
    {
        $this->assertEmpty($this->getDefaultWith(JournalEntry::class), 'JournalEntry should not auto-eager-load relationships');
    }

    public function test_sanction_entry_model_does_not_default_eager_load(): void
    {
        $this->assertEmpty($this->getDefaultWith(SanctionEntry::class), 'SanctionEntry should not auto-eager-load relationships');
    }
}
