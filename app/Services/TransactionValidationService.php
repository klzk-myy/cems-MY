<?php

namespace App\Services;

use App\Exceptions\Domain\InvalidCurrencyException;
use App\Exceptions\Domain\InvalidIpAddressException;
use App\Exceptions\Domain\PepApprovalRequiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Services\Contracts\TransactionValidationInterface;

class TransactionValidationService implements TransactionValidationInterface
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected ThresholdService $thresholdService,
        protected TellerAllocationService $tellerAllocationService,
        protected PepApprovalService $pepApprovalService,
    ) {}

    public function validateCurrency(string $currencyCode): void
    {
        $currency = Currency::where('code', $currencyCode)
            ->where('is_active', true)
            ->first();

        if (! $currency) {
            throw new InvalidCurrencyException($currencyCode);
        }
    }

    public function validateTillBalance(string $tillId, string $currencyCode): TillBalance
    {
        $tillBalance = TillBalance::where('till_id', $tillId)
            ->where('currency_code', $currencyCode)
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->first();

        if (! $tillBalance) {
            throw new TillBalanceMissingException($currencyCode, $tillId);
        }

        return $tillBalance;
    }

    public function validateIpAddress(?string $ipAddress): void
    {
        if ($ipAddress && ! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InvalidIpAddressException($ipAddress);
        }
    }

    public function validatePepRequirements(Customer $customer, array $data): void
    {
        if ($this->pepApprovalService->requiresHeadOfficeApproval($customer)) {
            if (! $this->pepApprovalService->hasApprovedApproval($customer)) {
                $pendingApproval = $this->pepApprovalService->requestApproval(
                    $customer,
                    $data['type'] ?? 'transaction'
                );

                throw new PepApprovalRequiredException(
                    "Senior Management approval required for PEP customer. Approval ID: {$pendingApproval->id}"
                );
            }
        }

        if ($customer->pep_status) {
            if (empty($data['source_of_funds'])) {
                throw new \InvalidArgumentException('Source of funds is required for PEP customers.');
            }
            if (empty($data['source_of_wealth'])) {
                throw new \InvalidArgumentException('Source of wealth is required for PEP customers per pd-00.md 14C.13.1(c).');
            }
        }
    }
}
