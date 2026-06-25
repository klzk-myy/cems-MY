<?php

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Exceptions\Domain\InvalidCurrencyException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\User;
use App\Services\Transaction\TransactionValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransactionValidationService::class);
    }

    #[Test]
    public function validate_throws_on_invalid_currency(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $this->expectException(InvalidCurrencyException::class);

        $this->service->validateCurrency('INVALID');
    }

    #[Test]
    public function validate_throws_on_missing_till_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $this->expectException(TillBalanceMissingException::class);

        $this->service->validateTillBalance('nonexistent', 'USD');
    }
}
