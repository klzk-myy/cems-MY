<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetThresholdRequest;
use App\Http\Requests\UpdateThresholdsRequest;
use App\Models\ThresholdAudit;
use App\Services\ThresholdService;
use App\Support\ThresholdMetadata;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * ThresholdController
 *
 * Admin UI for viewing effective threshold values, editing them (persisted
 * as audited DB overrides via ThresholdService), and resetting overrides
 * back to config defaults. Route middleware enforces role:manage_thresholds
 * + mfa.verified; mutations additionally require password.confirm.
 */
class ThresholdController extends Controller
{
    public function __construct(
        protected ThresholdService $thresholdService
    ) {}

    /**
     * Display every threshold grouped by functional domain with its config
     * default, active value, source, and last change, plus audit history.
     */
    public function index(): View
    {
        $this->requirePermission(Permission::ManageThresholds);

        $metadata = ThresholdMetadata::categories();
        $defaults = $this->thresholdService->configDefaults();
        $overrides = $this->thresholdService->latestOverrides();
        $activeOverrides = $this->thresholdService->activeOverrides();

        // Config categories without metadata still render (at the end) so a
        // new config section is never silently hidden from the page.
        $categoryOrder = array_unique(array_merge(
            array_keys($metadata),
            array_keys($defaults),
        ));

        $groups = [];
        $activeOverrideCount = 0;

        foreach ($categoryOrder as $category) {
            $meta = $metadata[$category] ?? [
                'label' => ucfirst(str_replace('_', ' ', $category)),
                'description' => '',
                'keys' => [],
            ];

            $keys = array_keys($defaults[$category] ?? []);

            // Persisted keys absent from config (e.g. a position limit added
            // for a currency with no file default) are shown and editable.
            foreach (array_keys($overrides) as $compound) {
                [$c, $k] = explode('.', $compound, 2);
                if ($c === $category && ! in_array($k, $keys, true)) {
                    $keys[] = $k;
                }
            }

            $rows = [];
            foreach ($keys as $key) {
                $override = $overrides["{$category}.{$key}"] ?? null;
                $configValue = $defaults[$category][$key] ?? null;
                $isOverridden = isset($activeOverrides["{$category}.{$key}"]);

                if ($isOverridden) {
                    $activeOverrideCount++;
                }

                $rows[] = [
                    'category' => $category,
                    'key' => $key,
                    'meta' => ThresholdMetadata::key($category, $key),
                    'config' => $configValue,
                    'active' => $isOverridden ? $override->new_value : $configValue,
                    'source' => $isOverridden ? 'db' : 'config',
                    'override' => $override,
                ];
            }

            $groups[$category] = ['meta' => $meta, 'rows' => $rows];
        }

        $this->authorize('viewAny', ThresholdAudit::class);

        $history = ThresholdAudit::with('user')
            ->latest('changed_at')
            ->latest('id')
            ->paginate(20);

        return view('admin.thresholds.index', compact('groups', 'activeOverrideCount', 'history'));
    }

    /**
     * Persist submitted threshold values as audited DB overrides.
     * Unchanged values are skipped silently by ThresholdService::set().
     */
    public function update(UpdateThresholdsRequest $request): RedirectResponse
    {
        $this->requirePermission(Permission::ManageThresholds);

        $validated = $request->validated();
        $changed = [];

        foreach ($validated['values'] ?? [] as $category => $keys) {
            foreach ($keys as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                if ($this->thresholdService->set($category, $key, $value, $validated['reason'])) {
                    $changed[] = "{$category}.{$key}";
                }
            }
        }

        $message = $changed === []
            ? 'No threshold values changed.'
            : 'Updated '.count($changed).' threshold(s): '.implode(', ', $changed);

        return redirect()->route('admin.thresholds.index')->with('success', $message);
    }

    /**
     * Revert one threshold to its config default (append-only audit row).
     */
    public function reset(ResetThresholdRequest $request): RedirectResponse
    {
        $this->requirePermission(Permission::ManageThresholds);

        $validated = $request->validated();
        $compound = "{$validated['category']}.".strtolower($validated['key']);

        $reverted = $this->thresholdService->reset(
            $validated['category'],
            $validated['key'],
            'Reset via admin threshold page'
        );

        return redirect()->route('admin.thresholds.index')->with(
            'success',
            $reverted
                ? "{$compound} reset to its config default."
                : "{$compound} is already at its config default."
        );
    }
}
