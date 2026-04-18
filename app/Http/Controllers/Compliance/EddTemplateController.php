<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\EddTemplate;
use App\Services\EddTemplateService;
use Illuminate\Http\Request;

class EddTemplateController extends Controller
{
    public function __construct(
        protected EddTemplateService $eddTemplateService
    ) {}

    public function index()
    {
        $templates = $this->eddTemplateService->getAllActiveTemplates();
        $statistics = $this->eddTemplateService->getTemplateStatistics();

        return view('compliance.edd-templates.index', compact('templates', 'statistics'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:pep,high_risk_country,unusual_pattern,sanction_match,large_transaction,high_risk_industry',
            'description' => 'nullable|string',
            'questions' => 'nullable|array',
        ]);

        $template = $this->eddTemplateService->createTemplate($request->all());

        return redirect()->route('compliance.edd-templates.show', $template->id)
            ->with('success', 'EDD Template created successfully');
    }

    public function show(EddTemplate $template)
    {
        return view('compliance.edd-templates.show', compact('template'));
    }

    public function update(Request $request, EddTemplate $template)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'questions' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->eddTemplateService->updateTemplate($template, $request->all());

        return redirect()->back()->with('success', 'Template updated successfully');
    }

    public function destroy(EddTemplate $template)
    {
        $template->update(['is_active' => false]);

        return redirect()->route('compliance.edd-templates.index')
            ->with('success', 'Template deactivated successfully');
    }

    public function duplicate(EddTemplate $template)
    {
        $clone = $template->duplicate();

        return redirect()->route('compliance.edd-templates.show', $clone->id)
            ->with('success', 'Template duplicated successfully');
    }
}
