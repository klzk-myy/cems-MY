<?php

namespace App\Http\Controllers;

use App\Enums\AmlRuleType;
use App\Http\Requests\StoreAmlRuleRequest;
use App\Http\Requests\UpdateAmlRuleRequest;
use App\Models\AmlRule;
use App\Models\SystemLog;
use App\Services\AmlRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * AML Rule Controller
 *
 * Handles CRUD operations for AML rules used in compliance monitoring.
 */
class AmlRuleController extends Controller
{
    /**
     * AML Rule Service for business logic.
     */
    protected AmlRuleService $amlRuleService;

    /**
     * Create a new controller instance.
     */
    public function __construct(AmlRuleService $amlRuleService)
    {
        $this->amlRuleService = $amlRuleService;
    }

    /**
     * Display a listing of AML rules.
     */
    public function index(): View
    {
        $rules = AmlRule::with('creator')
            ->orderBy('rule_type')
            ->orderBy('rule_name')
            ->paginate(20);

        $ruleTypes = AmlRuleType::cases();

        return view('compliance.rules.index', compact('rules', 'ruleTypes'));
    }

    /**
     * Show the form for creating a new AML rule.
     */
    public function create(): View
    {
        $ruleTypes = AmlRuleType::cases();
        $ruleTypeOptions = [];
        foreach ($ruleTypes as $type) {
            $ruleTypeOptions[$type->value] = [
                'label' => $type->label(),
                'description' => $type->description(),
                'default_conditions' => $type->defaultConditions(),
            ];
        }

        return view('compliance.rules.create', compact('ruleTypeOptions'));
    }

    /**
     * Store a newly created AML rule.
     */
    public function store(StoreAmlRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Validate conditions based on rule type
        $ruleType = AmlRuleType::from($validated['rule_type']);
        $conditionsValidation = $this->amlRuleService->validateConditions($ruleType, $validated['conditions']);

        if (! $conditionsValidation['valid']) {
            return back()
                ->withErrors($conditionsValidation['errors'])
                ->withInput();
        }

        $rule = AmlRule::create([
            'rule_code' => $validated['rule_code'],
            'rule_name' => $validated['rule_name'],
            'description' => $validated['description'] ?? null,
            'rule_type' => $ruleType,
            'conditions' => $validated['conditions'],
            'action' => $validated['action'],
            'risk_score' => $validated['risk_score'],
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
        ]);

        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'aml_rule_created',
            'entity_type' => 'AmlRule',
            'entity_id' => $rule->id,
            'description' => "AML Rule created: {$rule->rule_code}",
            'new_values' => $validated,
        ]);

        return redirect()
            ->route('compliance.rules.show', $rule)
            ->with('success', 'AML Rule created successfully.');
    }

    /**
     * Display the specified AML rule.
     */
    public function show(AmlRule $rule): View
    {
        $rule->load('creator');

        // Get rule hit history from SystemLog
        $hitHistory = SystemLog::where('action', 'aml_rule_triggered')
            ->where('new_values', 'LIKE', '%"rule_code":"'.$rule->rule_code.'"%')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get hit count for last 30 days
        $hitCount = SystemLog::where('action', 'aml_rule_triggered')
            ->where('new_values', 'LIKE', '%"rule_code":"'.$rule->rule_code.'"%')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return view('compliance.rules.show', compact('rule', 'hitHistory', 'hitCount'));
    }

    /**
     * Show the form for editing the specified AML rule.
     */
    public function edit(AmlRule $rule): View
    {
        $ruleTypes = AmlRuleType::cases();
        $ruleTypeOptions = [];
        foreach ($ruleTypes as $type) {
            $ruleTypeOptions[$type->value] = [
                'label' => $type->label(),
                'description' => $type->description(),
                'default_conditions' => $type->defaultConditions(),
            ];
        }

        return view('compliance.rules.edit', compact('rule', 'ruleTypeOptions'));
    }

    /**
     * Update the specified AML rule.
     */
    public function update(UpdateAmlRuleRequest $request, AmlRule $rule): RedirectResponse
    {
        $validated = $request->validated();

        // Validate conditions based on rule type
        $ruleType = AmlRuleType::from($validated['rule_type']);
        $conditionsValidation = $this->amlRuleService->validateConditions($ruleType, $validated['conditions']);

        if (! $conditionsValidation['valid']) {
            return back()
                ->withErrors($conditionsValidation['errors'])
                ->withInput();
        }

        $oldValues = $rule->toArray();

        $rule->update([
            'rule_code' => $validated['rule_code'],
            'rule_name' => $validated['rule_name'],
            'description' => $validated['description'] ?? null,
            'rule_type' => $ruleType,
            'conditions' => $validated['conditions'],
            'action' => $validated['action'],
            'risk_score' => $validated['risk_score'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'aml_rule_updated',
            'entity_type' => 'AmlRule',
            'entity_id' => $rule->id,
            'description' => "AML Rule updated: {$rule->rule_code}",
            'old_values' => $oldValues,
            'new_values' => $validated,
        ]);

        return redirect()
            ->route('compliance.rules.show', $rule)
            ->with('success', 'AML Rule updated successfully.');
    }

    /**
     * Toggle the active status of an AML rule.
     */
    public function toggle(AmlRule $rule): RedirectResponse
    {
        $newStatus = ! $rule->is_active;

        $rule->update([
            'is_active' => $newStatus,
        ]);

        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => $newStatus ? 'aml_rule_activated' : 'aml_rule_deactivated',
            'entity_type' => 'AmlRule',
            'entity_id' => $rule->id,
            'description' => 'AML Rule '.($newStatus ? 'activated' : 'deactivated').": {$rule->rule_code}",
        ]);

        $message = $newStatus ? 'Rule activated successfully.' : 'Rule deactivated successfully.';

        return redirect()
            ->route('compliance.rules.index')
            ->with('success', $message);
    }

    /**
     * Remove the specified AML rule (soft delete).
     */
    public function destroy(AmlRule $rule): RedirectResponse
    {
        $ruleCode = $rule->rule_code;

        // Soft delete by deactivating instead
        $rule->update([
            'is_active' => false,
        ]);

        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'aml_rule_deleted',
            'entity_type' => 'AmlRule',
            'entity_id' => $rule->id,
            'description' => "AML Rule deleted: {$ruleCode}",
        ]);

        return redirect()
            ->route('compliance.rules.index')
            ->with('success', 'AML Rule deleted successfully.');
    }
}
