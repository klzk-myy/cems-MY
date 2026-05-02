<?php

namespace App\Livewire\Compliance\Edd;

use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Livewire\BaseComponent;
use App\Models\Customer;
use App\Models\EddTemplate;
use App\Models\EnhancedDiligenceRecord;
use App\Models\User;
use App\Services\EddService;
use App\Services\EddTemplateService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class Form extends BaseComponent
{
    use WithFileUploads;

    public ?EnhancedDiligenceRecord $record = null;

    public int $currentStep = 1;

    public int $maxSteps = 3;

    // Form fields
    public ?int $customerId = null;

    public ?string $riskLevel = '';

    public ?string $sourceOfFunds = '';

    public ?string $sourceOfFundsDescription = '';

    public ?string $purposeOfTransaction = '';

    public ?string $businessJustification = '';

    public ?string $employmentStatus = '';

    public ?string $employerName = '';

    public ?string $employerAddress = '';

    public ?string $annualIncomeRange = '';

    public ?string $estimatedNetWorth = '';

    public ?string $sourceOfWealth = '';

    public ?string $sourceOfWealthDescription = '';

    public ?string $additionalInformation = '';

    public ?int $templateId = null;

    public bool $isReadOnly = false;

    public bool $showRejectModal = false;

    public ?string $rejectReason = '';

    protected EddService $eddService;

    protected EddTemplateService $templateService;

    public function __construct()
    {
        $this->eddService = app(EddService::class);
        $this->templateService = app(EddTemplateService::class);
    }

    public function mount(?EnhancedDiligenceRecord $record = null): void
    {
        if ($record && $record->exists) {
            $this->record = $record;
            $this->loadRecordData();
            $this->isReadOnly = $record->status !== EddStatus::Incomplete &&
                                 $record->status !== EddStatus::PendingQuestionnaire;
        } else {
            $this->record = null;
            $this->riskLevel = EddRiskLevel::Medium->value;
        }
    }

    public function loadRecordData(): void
    {
        if (! $this->record) {
            return;
        }

        $this->customerId = $this->record->customer_id;
        $this->riskLevel = $this->record->risk_level;
        $this->sourceOfFunds = $this->record->source_of_funds;
        $this->sourceOfFundsDescription = $this->record->source_of_funds_description;
        $this->purposeOfTransaction = $this->record->purpose_of_transaction;
        $this->businessJustification = $this->record->business_justification;
        $this->employmentStatus = $this->record->employment_status;
        $this->employerName = $this->record->employer_name;
        $this->employerAddress = $this->record->employer_address;
        $this->annualIncomeRange = $this->record->annual_income_range;
        $this->estimatedNetWorth = $this->record->estimated_net_worth;
        $this->sourceOfWealth = $this->record->source_of_wealth;
        $this->sourceOfWealthDescription = $this->record->source_of_wealth_description;
        $this->additionalInformation = $this->record->additional_information;
        $this->templateId = $this->record->edd_template_id;
    }

    #[Computed]
    protected function availableCustomers(): array
    {
        return Customer::orderBy('full_name')
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'full_name' => $c->full_name,
                'ic_number' => $c->ic_number ?? 'N/A',
            ])
            ->toArray();
    }

    #[Computed]
    protected function availableTemplates(): array
    {
        return EddTemplate::active()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => $t->type->label(),
            ])
            ->toArray();
    }

    #[Computed]
    protected function availableOfficers(): array
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['compliance', 'manager']);
        })
            ->where('is_active', true)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
            ])
            ->toArray();
    }

    public function nextStep(): void
    {
        if ($this->currentStep < $this->maxSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= $this->maxSteps) {
            $this->currentStep = $step;
        }
    }

    public function save(): void
    {
        $this->validate($this->getValidationRules());

        $data = [
            'customer_id' => $this->customerId,
            'risk_level' => $this->riskLevel,
            'source_of_funds' => $this->sourceOfFunds,
            'source_of_funds_description' => $this->sourceOfFundsDescription,
            'purpose_of_transaction' => $this->purposeOfTransaction,
            'business_justification' => $this->businessJustification,
            'employment_status' => $this->employmentStatus,
            'employer_name' => $this->employerName,
            'employer_address' => $this->employerAddress,
            'annual_income_range' => $this->annualIncomeRange,
            'estimated_net_worth' => $this->estimatedNetWorth,
            'source_of_wealth' => $this->sourceOfWealth,
            'source_of_wealth_description' => $this->sourceOfWealthDescription,
            'additional_information' => $this->additionalInformation,
            'edd_template_id' => $this->templateId,
        ];

        try {
            if ($this->record && $this->record->exists) {
                $this->eddService->updateEddRecord($this->record, $data);
                $this->success('EDD record updated successfully');
            } else {
                $record = EnhancedDiligenceRecord::create($data);
                $this->record = $record;
                $this->success('EDD record created successfully');
            }
        } catch (\Exception $e) {
            $this->error('Failed to save EDD record: '.$e->getMessage());
        }
    }

    public function submitForReview(): void
    {
        if (! $this->record) {
            $this->save();
        }

        if (! $this->record) {
            return;
        }

        try {
            $this->eddService->submitForReview($this->record);
            $this->record->refresh();
            $this->success('EDD record submitted for review');
        } catch (\Exception $e) {
            $this->error('Failed to submit: '.$e->getMessage());
        }
    }

    public function approve(?string $notes = null): void
    {
        if (! $this->record) {
            return;
        }

        try {
            $this->eddService->approve($this->record, auth()->user(), $notes);
            $this->record->refresh();
            $this->success('EDD record approved');
        } catch (\Exception $e) {
            $this->error('Failed to approve: '.$e->getMessage());
        }
    }

    public function reject(string $reason): void
    {
        if (! $this->record) {
            return;
        }

        try {
            $this->eddService->reject($this->record, auth()->user(), $reason);
            $this->record->refresh();
            $this->success('EDD record rejected');
        } catch (\Exception $e) {
            $this->error('Failed to reject: '.$e->getMessage());
        }
    }

    protected function getValidationRules(): array
    {
        return [
            'customerId' => 'required|exists:customers,id',
            'riskLevel' => 'required|in:Low,Medium,High,Critical',
            'sourceOfFunds' => 'required|string|max:255',
            'sourceOfFundsDescription' => 'nullable|string|max:1000',
            'purposeOfTransaction' => 'required|string|max:500',
            'businessJustification' => 'nullable|string|max:1000',
            'employmentStatus' => 'nullable|string|max:100',
            'employerName' => 'nullable|string|max:255',
            'employerAddress' => 'nullable|string|max:500',
            'annualIncomeRange' => 'nullable|string|max:100',
            'estimatedNetWorth' => 'nullable|string|max:100',
            'sourceOfWealth' => 'nullable|string|max:255',
            'sourceOfWealthDescription' => 'nullable|string|max:1000',
            'additionalInformation' => 'nullable|string|max:2000',
            'templateId' => 'nullable|exists:edd_templates,id',
        ];
    }

    public function render(): View
    {
        return view('livewire.compliance.edd.form');
    }
}
