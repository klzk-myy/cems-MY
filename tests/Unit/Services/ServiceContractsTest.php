<?php

namespace Tests\Unit\Services;

use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\Contracts\AccountingServiceInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\ComplianceServiceInterface;
use App\Services\Contracts\CustomerScreeningServiceInterface;
use App\Services\Contracts\CustomerServiceInterface;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\Contracts\TellerAllocationServiceInterface;
use App\Services\Contracts\ThresholdServiceInterface;
use App\Services\Contracts\TransactionMonitoringServiceInterface;
use App\Services\CustomerScreeningService;
use App\Services\CustomerService;
use App\Services\RateManagementService;
use App\Services\TellerAllocationService;
use App\Services\ThresholdService;
use App\Services\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_implement_their_interfaces(): void
    {
        $mappings = [
            CustomerService::class => CustomerServiceInterface::class,
            RateManagementService::class => RateManagementServiceInterface::class,
            AuditService::class => AuditServiceInterface::class,
            ComplianceService::class => ComplianceServiceInterface::class,
            AccountingService::class => AccountingServiceInterface::class,
            TransactionMonitoringService::class => TransactionMonitoringServiceInterface::class,
            TellerAllocationService::class => TellerAllocationServiceInterface::class,
            CustomerScreeningService::class => CustomerScreeningServiceInterface::class,
            ThresholdService::class => ThresholdServiceInterface::class,
        ];

        foreach ($mappings as $concrete => $interface) {
            $this->assertTrue(
                is_subclass_of($concrete, $interface),
                "{$concrete} must implement {$interface}"
            );
        }
    }
}
