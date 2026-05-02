<?php

namespace App\Livewire\Compliance\Cases;

use App\Enums\CaseNoteType;
use App\Enums\CaseResolution;
use App\Livewire\BaseComponent;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\User;
use App\Services\Compliance\CaseManagementService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class Show extends BaseComponent
{
    public ?ComplianceCase $case = null;

    public string $newNote = '';

    public bool $isInternalNote = true;

    protected CaseManagementService $caseManagementService;

    public function __construct()
    {
        $this->caseManagementService = app(CaseManagementService::class);
    }

    public function mount(ComplianceCase $case): void
    {
        $this->case = $case->load([
            'customer',
            'assignee',
            'notes.author',
            'documents',
            'links',
            'alerts',
        ]);
    }

    #[Computed]
    protected function availableOfficers(): array
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['compliance', 'manager']);
        })
            ->where('is_active', true)
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'username' => $u->username])
            ->toArray();
    }

    #[Computed]
    protected function unlinkedAlerts(): array
    {
        return Alert::whereNull('case_id')
            ->where('customer_id', $this->case->customer_id)
            ->get()
            ->map(fn ($a) => ['id' => $a->id, 'type' => $a->type->label(), 'reason' => $a->reason])
            ->toArray();
    }

    public function addNote(): void
    {
        if (empty($this->newNote)) {
            $this->error('Note content is required');

            return;
        }

        try {
            $this->caseManagementService->addNote(
                $this->case,
                auth()->id(),
                CaseNoteType::General,
                $this->newNote,
                $this->isInternalNote
            );

            $this->case->refresh();
            $this->newNote = '';
            $this->success('Note added successfully');
        } catch (\Exception $e) {
            $this->error('Failed to add note: '.$e->getMessage());
        }
    }

    public function assign(int $userId): void
    {
        try {
            $this->caseManagementService->assignToOfficer($this->case, $userId);
            $this->case->refresh();
            $this->success('Case assigned successfully');
        } catch (\Exception $e) {
            $this->error('Failed to assign case: '.$e->getMessage());
        }
    }

    public function escalate(): void
    {
        try {
            $this->caseManagementService->escalateCase($this->case);
            $this->case->refresh();
            $this->success('Case escalated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to escalate case: '.$e->getMessage());
        }
    }

    public function close(string $resolution, ?string $notes = null): void
    {
        try {
            $caseResolution = CaseResolution::from($resolution);
            $this->caseManagementService->closeCase($this->case, $caseResolution, $notes);
            $this->case->refresh();
            $this->success('Case closed successfully');
        } catch (\Exception $e) {
            $this->error('Failed to close case: '.$e->getMessage());
        }
    }

    public function linkAlert(int $alertId): void
    {
        try {
            $alert = Alert::findOrFail($alertId);
            $this->caseManagementService->linkAlertToCase($alert, $this->case);
            $this->case->refresh();
            $this->success('Alert linked successfully');
        } catch (\Exception $e) {
            $this->error('Failed to link alert: '.$e->getMessage());
        }
    }

    public function unlinkAlert(int $alertId): void
    {
        try {
            Alert::where('id', $alertId)->update(['case_id' => null]);
            $this->case->refresh();
            $this->success('Alert unlinked successfully');
        } catch (\Exception $e) {
            $this->error('Failed to unlink alert: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.compliance.cases.show', [
            'caseResolutions' => CaseResolution::cases(),
        ]);
    }
}
