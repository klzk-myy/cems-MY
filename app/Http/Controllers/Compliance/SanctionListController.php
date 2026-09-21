<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\ImportStatus;
use App\Enums\SanctionStatus;
use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Concerns\SanctionEntryNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSanctionEntryRequest;
use App\Http\Requests\UpdateSanctionEntryRequest;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\SanctionImportLog;
use App\Models\Compliance\SanctionList;
use App\Services\Compliance\SanctionsOrchestrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SanctionListController extends Controller
{
    use HandlesControllerErrors, SanctionEntryNormalizer;

    public function __construct(
        protected SanctionsOrchestrationService $orchestrationService,
    ) {}

    public function index(): View
    {
        $lists = SanctionList::withCount('entries')
            ->orderBy('name')
            ->paginate(25)
            ->through(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'list_type' => $list->list_type->value,
                'source_url' => $list->source_url,
                'source_format' => $list->source_format?->value,
                'update_status' => $list->update_status->value,
                'last_synced_at' => $list->last_updated_at?->toIso8601String(),
                'status' => $list->update_status->value,
                'entries_count' => $list->entries_count,
            ]);

        return view('compliance.sanctions.index', compact('lists'));
    }

    public function show(int $id): View|RedirectResponse
    {
        $list = SanctionList::withCount('entries')->find($id);

        if (! $list) {
            return redirect()->route('compliance.sanctions.index')
                ->with('error', 'Sanction list not found');
        }

        return view('compliance.sanctions.show', compact('list'));
    }

    public function entriesIndex(Request $request): View
    {
        $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
        $status = $request->get('status', SanctionStatus::Active->value);

        $query = SanctionEntry::filtered(
            is_string($status) ? $status : null,
            $request->filled('list_id') ? (int) $request->list_id : null,
            $request->filled('search') ? (string) $request->search : null
        );

        $entriesPaginated = $query->paginate($perPage);

        $entries = $entriesPaginated->map(fn ($entry) => $entry->toEntrySummaryArray());

        $pagination = [
            'current_page' => $entriesPaginated->currentPage(),
            'last_page' => $entriesPaginated->lastPage(),
            'per_page' => $entriesPaginated->perPage(),
            'total' => $entriesPaginated->total(),
        ];

        $lists = SanctionList::orderBy('name')->get(['id', 'name']);

        return view('compliance.sanctions.entries.index', compact('entries', 'pagination', 'lists'));
    }

    public function showEntry(int $id): View|RedirectResponse
    {
        $entry = SanctionEntry::with('sanctionList')->find($id);

        if (! $entry) {
            return redirect()->route('compliance.sanctions.entries.index')
                ->with('error', 'Sanction entry not found');
        }

        return view('compliance.sanctions.entries.show', ['sanctionEntry' => $entry]);
    }

    public function createEntry(): View
    {
        $lists = SanctionList::orderBy('name')->get(['id', 'name']);

        return view('compliance.sanctions.entries.create', compact('lists'));
    }

    public function storeEntry(StoreSanctionEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $normalized = $this->normalizeEntityName($validated['entity_name']);

        SanctionEntry::create(SanctionEntry::buildForCreate($validated, $normalized));

        return redirect()->route('compliance.sanctions.entries.index')
            ->with('success', 'Sanction entry created successfully');
    }

    public function editEntry(SanctionEntry $entry): View
    {
        return view('compliance.sanctions.entries.edit', ['sanctionEntry' => $entry]);
    }

    public function updateEntry(UpdateSanctionEntryRequest $request, SanctionEntry $entry): RedirectResponse
    {
        $request->merge([
            'entity_type' => ucfirst($request->input('entity_type', '')),
        ]);

        $validated = $request->validated();

        if (isset($validated['date_listed'])) {
            $validated['listing_date'] = $validated['date_listed'];
        }

        $normalized = $this->normalizeEntityName($validated['entity_name']);

        $entry->update(SanctionEntry::buildForUpdate($validated, $normalized));

        return redirect()->route('compliance.sanctions.entries.show', $entry)
            ->with('success', 'Sanction entry updated successfully');
    }

    public function importLogs(Request $request): View
    {
        $query = SanctionImportLog::with(['sanctionList', 'user']);

        if ($request->filled('status')) {
            // Whitelist against the enum — the status column only ever holds
            // ImportStatus values.
            $importStatus = ImportStatus::tryFrom((string) $request->input('status'));
            if ($importStatus !== null) {
                $query->where('status', $importStatus->value);
            }
        }

        if ($request->filled('source')) {
            $source = $request->input('source');
            $query->whereHas('sanctionList', fn ($listQuery) => $listQuery->where('slug', $source));
        }

        $logs = $query->orderBy('imported_at', 'desc')
            ->paginate(50)
            ->through(fn ($log) => $log->toSummaryArray());

        $sources = SanctionList::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->mapWithKeys(fn ($list) => [$list->slug => $list->name]);

        return view('compliance.sanctions.import-logs.index', compact('logs', 'sources'));
    }

    public function triggerImport(int $listId): RedirectResponse
    {
        $list = SanctionList::find($listId);

        if (! $list) {
            return redirect()->back()->with('error', 'Sanction list not found');
        }

        try {
            $result = $this->orchestrationService->syncSanctionsList($list, true);

            if (! $result['success']) {
                return redirect()->back()->with('error', $result['error'] ?? 'Import failed');
            }

            return redirect()->back()->with('success', 'Import triggered successfully');
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Sanction import trigger failed', 'Failed to trigger import. Please try again.');
        }
    }
}
