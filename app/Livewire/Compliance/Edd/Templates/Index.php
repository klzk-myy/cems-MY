<?php

namespace App\Livewire\Compliance\Edd\Templates;

use App\Enums\EddTemplateType;
use App\Livewire\BaseComponent;
use App\Models\EddTemplate;
use App\Services\EddTemplateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\WithFileUploads;

class Index extends BaseComponent
{
    use WithFileUploads;

    public string $search = '';

    public ?string $type = '';

    public ?string $showModal = null;

    // Form fields
    public ?string $name = '';

    public ?string $templateType = '';

    public ?string $description = '';

    public array $questions = ['sections' => []];

    public bool $isActive = true;

    public ?EddTemplate $editingTemplate = null;

    public static function getRoutePath(): string
    {
        return '/compliance/edd-templates';
    }

    protected function getTemplates(): Collection
    {
        $query = EddTemplate::query();

        if (! empty($this->search)) {
            $search = $this->search;
            $query->where('name', 'like', "%{$search}%");
        }

        if (! empty($this->type) && EddTemplateType::tryFrom($this->type) !== null) {
            $query->where('type', $this->type);
        }

        return $query->withCount('enhancedDiligenceRecords')
            ->orderByDesc('created_at')
            ->get();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = 'create';
    }

    public function openEditModal(EddTemplate $template): void
    {
        $this->editingTemplate = $template;
        $this->name = $template->name;
        $this->templateType = $template->type->value;
        $this->description = $template->description;
        $this->questions = $template->questions ?? ['sections' => []];
        $this->isActive = $template->is_active;
        $this->showModal = 'edit';
    }

    public function closeModal(): void
    {
        $this->showModal = null;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->templateType = '';
        $this->description = '';
        $this->questions = ['sections' => []];
        $this->isActive = true;
        $this->editingTemplate = null;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'templateType' => 'required|in:pep,high_risk_country,unusual_pattern,sanction_match,large_transaction,high_risk_industry',
            'description' => 'nullable|string|max:1000',
            'questions' => 'required|array',
            'isActive' => 'boolean',
        ]);

        $templateService = app(EddTemplateService::class);

        $data = [
            'name' => $this->name,
            'type' => $this->templateType,
            'description' => $this->description,
            'questions' => $this->questions,
            'is_active' => $this->isActive,
        ];

        try {
            if ($this->editingTemplate) {
                $templateService->updateTemplate($this->editingTemplate, $data);
                $this->success('Template updated successfully');
            } else {
                $templateService->createTemplate($data);
                $this->success('Template created successfully');
            }
            $this->closeModal();
        } catch (\Exception $e) {
            $this->error('Failed to save template: '.$e->getMessage());
        }
    }

    public function duplicate(EddTemplate $template): void
    {
        try {
            $clone = $template->duplicate();
            $this->success("Template duplicated as '{$clone->name}'");
        } catch (\Exception $e) {
            $this->error('Failed to duplicate template: '.$e->getMessage());
        }
    }

    public function toggleActive(EddTemplate $template): void
    {
        try {
            $template->update(['is_active' => ! $template->is_active]);
            $status = $template->is_active ? 'activated' : 'deactivated';
            $this->success("Template {$status}");
        } catch (\Exception $e) {
            $this->error('Failed to update template: '.$e->getMessage());
        }
    }

    public function delete(EddTemplate $template): void
    {
        try {
            if ($template->enhanced_diligence_records_count > 0) {
                $this->error('Cannot delete template that has been used in EDD records');

                return;
            }
            $template->delete();
            $this->success('Template deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete template: '.$e->getMessage());
        }
    }

    public function addSection(): void
    {
        $sectionNumber = count($this->questions['sections']) + 1;
        $this->questions['sections'][] = [
            'title' => "Section {$sectionNumber}",
            'questions' => [],
        ];
    }

    public function removeSection(int $index): void
    {
        if (isset($this->questions['sections'][$index])) {
            unset($this->questions['sections'][$index]);
            $this->questions['sections'] = array_values($this->questions['sections']);
        }
    }

    public function addQuestion(int $sectionIndex): void
    {
        if (isset($this->questions['sections'][$sectionIndex])) {
            $questionNumber = count($this->questions['sections'][$sectionIndex]['questions']) + 1;
            $this->questions['sections'][$sectionIndex]['questions'][] = [
                'id' => 'q_'.time().'_'.$questionNumber,
                'text' => '',
                'type' => 'text',
                'required' => false,
                'options' => [],
            ];
        }
    }

    public function removeQuestion(int $sectionIndex, int $questionIndex): void
    {
        if (isset($this->questions['sections'][$sectionIndex]['questions'][$questionIndex])) {
            unset($this->questions['sections'][$sectionIndex]['questions'][$questionIndex]);
            $this->questions['sections'][$sectionIndex]['questions'] = array_values(
                $this->questions['sections'][$sectionIndex]['questions']
            );
        }
    }

    public function render(): View
    {
        return view('livewire.compliance.edd.templates.index', [
            'templates' => $this->getTemplates(),
            'templateTypes' => EddTemplateType::cases(),
        ]);
    }
}
