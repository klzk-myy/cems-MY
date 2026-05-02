<?php

namespace App\Livewire\Compliance\Alerts;

use App\Enums\FlagStatus;
use App\Livewire\BaseComponent;
use App\Models\Alert;
use App\Models\User;
use App\Services\AlertTriageService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class Show extends BaseComponent
{
    public ?Alert $alert = null;

    protected AlertTriageService $alertTriageService;

    public function __construct()
    {
        $this->alertTriageService = app(AlertTriageService::class);
    }

    public function mount(Alert $alert): void
    {
        $this->alert = $alert->load([
            'customer',
            'assignedTo',
            'flaggedTransaction',
            'flaggedTransaction.transaction',
            'case',
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

    public function assign(int $userId): void
    {
        try {
            $this->alertTriageService->assignToOfficer($this->alert, $userId);
            $this->alert->refresh();
            $this->success('Alert assigned successfully');
        } catch (\Exception $e) {
            $this->error('Failed to assign alert: '.$e->getMessage());
        }
    }

    public function resolve(?string $notes = null): void
    {
        try {
            $this->alertTriageService->resolveAlert($this->alert, auth()->id(), $notes);
            $this->alert->refresh();
            $this->success('Alert resolved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to resolve alert: '.$e->getMessage());
        }
    }

    public function dismiss(): void
    {
        try {
            $this->alert->update(['status' => FlagStatus::Rejected]);
            $this->alert->refresh();
            $this->success('Alert dismissed');
        } catch (\Exception $e) {
            $this->error('Failed to dismiss alert: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.compliance.alerts.show');
    }
}
