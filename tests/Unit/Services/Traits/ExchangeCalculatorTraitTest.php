<?php

namespace Tests\Unit\Services\Traits;

use App\Enums\TransactionType;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\System\MathService;
use App\Services\Traits\ExchangeCalculatorTrait;
use App\Services\Transaction\ExchangeCalculator;
use App\Services\Transaction\TransactionImportService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locks down the shared ExchangeCalculator resolution.
 *
 * TransactionCreationService and TransactionImportService previously each
 * carried a private resolveExchangeCalculator() copy. Both now compose
 * ExchangeCalculatorTrait, whose calculator is always constructor-injected —
 * there is no container fallback.
 */
class ExchangeCalculatorTraitTest extends TestCase
{
    #[Test]
    public function transaction_creation_service_composes_the_shared_resolver(): void
    {
        $this->assertUsesSharedResolver(app(TransactionCreationServiceInterface::class));
    }

    #[Test]
    public function transaction_import_service_composes_the_shared_resolver(): void
    {
        $this->assertUsesSharedResolver(app(TransactionImportService::class));
    }

    #[Test]
    public function resolver_returns_the_injected_calculator(): void
    {
        $injected = new ExchangeCalculator(new MathService);

        $this->assertSame($injected, (new ExchangeCalculatorResolverStub($injected))->resolve());
    }

    #[Test]
    public function the_injected_calculator_produces_the_expected_conversion(): void
    {
        $result = (new ExchangeCalculatorResolverStub(app(ExchangeCalculator::class)))->resolve()->calculate(
            TransactionType::Buy,
            'USD',
            '100.00',
            '4.500000'
        );

        $this->assertSame('450.0000', $result['amount_myr']);
    }

    #[Test]
    public function no_service_defines_its_own_resolver_copy(): void
    {
        // A trait-imported method reports the trait's file as its definition
        // site; a class-defined copy would report the service's own file.
        $traitFile = (new \ReflectionClass(ExchangeCalculatorTrait::class))->getFileName();

        foreach ([
            app(TransactionCreationServiceInterface::class),
            app(TransactionImportService::class),
        ] as $service) {
            $method = new \ReflectionMethod($service, 'resolveExchangeCalculator');

            $this->assertSame(
                $traitFile,
                $method->getFileName(),
                get_class($service).' should not carry a duplicate resolver; use ExchangeCalculatorTrait'
            );
        }
    }

    private function assertUsesSharedResolver(object $service): void
    {
        $this->assertContains(
            ExchangeCalculatorTrait::class,
            array_keys(class_uses_recursive($service))
        );

        $this->assertTrue(method_exists($service, 'resolveExchangeCalculator'));
    }
}

final class ExchangeCalculatorResolverStub
{
    use ExchangeCalculatorTrait;

    public function __construct(protected ExchangeCalculator $exchangeCalculator) {}

    public function resolve(): ExchangeCalculator
    {
        return $this->resolveExchangeCalculator();
    }
}
