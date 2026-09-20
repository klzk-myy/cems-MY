<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SanctionListType;
use App\Enums\SanctionSourceFormat;
use App\Enums\UpdateStatus;
use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSanctionSourceRequest;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Services\Compliance\SanctionsOrchestrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin-only management of sanctions list sources: add a source, remove it
 * (soft-deleting the list and its entries so screening stops matching them),
 * or trigger an immediate sync of the remote feed.
 */
class SanctionSourceController extends Controller
{
    use HandlesControllerErrors;

    public function __construct(
        protected SanctionsOrchestrationService $orchestrationService,
    ) {}

    public function index(): View
    {
        $lists = SanctionList::withCount('entries')
            ->orderBy('name')
            ->paginate(25);

        $history = SanctionImportLog::with(['sanctionList', 'user'])
            ->orderByDesc('imported_at')
            ->paginate(15, ['*'], 'history');

        return view('admin.sanctions.index', [
            'lists' => $lists,
            'history' => $history,
            'listTypes' => collect(SanctionListType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->value])->all(),
            'sourceFormats' => collect(SanctionSourceFormat::cases())->mapWithKeys(fn ($f) => [$f->value => $f->value])->all(),
        ]);
    }

    public function store(StoreSanctionSourceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $slug = Str::slug($validated['name']);
        $suffix = 2;
        while (SanctionList::withTrashed()->where('slug', $slug)->exists()) {
            $slug = Str::slug($validated['name']).'-'.$suffix++;
        }

        SanctionList::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'list_type' => $validated['list_type'],
            'source_url' => $validated['source_url'] ?? null,
            'source_format' => $validated['source_format'] ?? null,
            'uploaded_by' => $request->user()->id,
            'is_active' => true,
            'update_status' => UpdateStatus::NeverRun,
        ]);

        return redirect()->route('admin.sanctions.index')
            ->with('success', 'Sanction source added.');
    }

    public function sync(SanctionList $list): RedirectResponse
    {
        try {
            $result = $this->orchestrationService->syncSanctionsList($list, true);

            if (! ($result['success'] ?? false)) {
                return back()->with('error', $result['error'] ?? 'Import failed');
            }

            return back()->with('success', "'{$list->name}' updated — {$result['imported']} entries imported.");
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Sanction import failed', 'Failed to update the list. Please try again.');
        }
    }

    public function destroy(SanctionList $list): RedirectResponse
    {
        // Entries soft-delete with the list so the screening pool stops
        // matching them; restrictOnDelete on list_id only guards hard
        // deletes, so the FK stays intact.
        $list->entries()->delete();
        $list->delete();

        return redirect()->route('admin.sanctions.index')
            ->with('success', "'{$list->name}' removed.");
    }
}
