<?php

namespace Tests\Unit\Exceptions\Domain;

use App\Exceptions\Domain\TransactionApprovalException;
use App\Exceptions\Domain\TransactionCreationException;
use App\Exceptions\Domain\TransactionException;
use App\Exceptions\Domain\TransactionValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionExceptionsTest extends TestCase
{
    #[Test]
    public function transaction_exceptions_extend_transaction_exception(): void
    {
        $this->assertInstanceOf(TransactionException::class, new TransactionValidationException('validation failed'));
        $this->assertInstanceOf(TransactionException::class, new TransactionCreationException('creation failed'));
        $this->assertInstanceOf(TransactionException::class, new TransactionApprovalException('approval failed'));
    }

    #[Test]
    public function transaction_exceptions_return_422_status_code(): void
    {
        $this->assertSame(422, (new TransactionValidationException('validation failed'))->getStatusCode());
        $this->assertSame(422, (new TransactionCreationException('creation failed'))->getStatusCode());
        $this->assertSame(422, (new TransactionApprovalException('approval failed'))->getStatusCode());
    }
}
