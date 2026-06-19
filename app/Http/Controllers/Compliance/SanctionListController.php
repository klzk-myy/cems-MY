<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Concerns\SanctionEntryNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSanctionEntryRequest;
use App\Http\Requests\UpdateSanctionEntryRequest;
use App\Models\SanctionEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Services\Compliance\SanctionsOrchestrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SanctionListController extends Controller
{
    use SanctionEntryNormalizer;

    public function __construct(
        protected SanctionsOrchestrationService $orchestrationService,
    ) {}

    public function index(): View
    {
        $lists = SanctionList::withCount('entries')
            ->orderBy('name')
            ->get()
            ->map(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'list_type' => $list->list_type?->value ?? (string) $list->list_type,
                'source_url' => $list->source_url,
                'source_format' => $list->source_format,
                'update_frequency' => $list->update_frequency,
                'last_synced_at' => $list->last_updated_at?->toIso8601String(),
                'status' => $list->update_status?->value ?? (string) $list->update_status,
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
        $perPage = $request->get('per_page', 50);
        $status = $request->get('status', 'active');

        $query = SanctionEntry::with('sanctionList')
            ->when($request->list_id, fn ($q, $id) => $q->where('list_id', $id))
            ->when($request->search, fn ($q, $search) => $q->where('entity_name', 'like', "%{$search}%"))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('entity_name');

        $entriesPaginated = $query->paginate($perPage);

        $entries = $entriesPaginated->map(fn ($entry) => [
            'id' => $entry->id,
            'entity_name' => $entry->entity_name,
            'entity_type' => $entry->entity_type,
            'list_source' => $entry->list_source,
            'list' => [
                'id' => $entry->sanctionList?->id,
                'name' => $entry->sanctionList?->name,
            ],
            'nationality' => $entry->nationality,
            'date_of_birth' => $entry->date_of_birth?->format('Y-m-d'),
            'reference_number' => $entry->reference_number,
            'status' => $entry->status,
            'listing_date' => $entry->listing_date?->format('Y-m-d'),
        ]);

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

        $entryData = [
            'id' => $entry->id,
            'entity_name' => $entry->entity_name,
            'entity_type' => $entry->entity_type,
            'list' => [
                'id' => $entry->sanctionList?->id,
                'name' => $entry->sanctionList?->name,
            ],
            'nationality' => $entry->nationality,
            'date_of_birth' => $entry->date_of_birth?->format('Y-m-d'),
            'reference_number' => $entry->reference_number,
            'status' => $entry->status,
            'listing_date' => $entry->listing_date?->format('Y-m-d'),
        ];

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

        $validated['aliases'] = $request->has('aliases')
            ? array_filter(array_map('trim', explode("\n", $request->input('aliases'))))
            : null;

        $normalized = $this->normalizeEntityName($validated['entity_name']);

        SanctionEntry::create([
            'list_id' => $validated['list_id'],
            'entity_name' => $validated['entity_name'],
            'entity_type' => $validated['entity_type'],
            'aliases' => $validated['aliases'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'listing_date' => $validated['listing_date'] ?? null,
            'details' => $validated['details'] ?? null,
            'normalized_name' => $normalized['normalized_name'],
            'soundex_code' => $normalized['soundex_code'],
            'metaphone_code' => $normalized['metaphone_code'],
            'status' => 'active',
        ]);

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

        $validated['aliases'] = $request->has('aliases')
            ? array_filter(array_map('trim', explode("\n", $request->input('aliases'))))
            : null;

        $normalized = $this->normalizeEntityName($validated['entity_name']);

        $updateData = [
            'list_source' => $validated['list_source'] ?? null,
            'entity_name' => $validated['entity_name'],
            'entity_type' => $validated['entity_type'],
            'aliases' => $validated['aliases'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'listing_date' => $validated['date_listed'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'details' => $validated['details'] ?? null,
        ];

        $updateData['normalized_name'] = $normalized['normalized_name'];
        $updateData['soundex_code'] = $normalized['soundex_code'];
        $updateData['metaphone_code'] = $normalized['metaphone_code'];

        $entry->update($updateData);

        return redirect()->route('compliance.sanctions.entries.show', $entry)
            ->with('success', 'Sanction entry updated successfully');
    }

    public function importLogs(): View
    {
        $logs = SanctionImportLog::with('sanctionList')
            ->orderBy('imported_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'list' => [
                    'id' => $log->sanctionList?->id,
                    'name' => $log->sanctionList?->name,
                ],
                'imported_at' => $log->imported_at->toIso8601String(),
                'records_added' => $log->records_added,
                'records_updated' => $log->records_updated,
                'records_deactivated' => $log->records_deactivated,
                'status' => $log->status,
                'error_message' => $log->error_message,
                'triggered_by' => $log->triggered_by,
            ]);

        return view('compliance.sanctions.import-logs.index', compact('logs'));
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
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to trigger import: '.$e->getMessage());
        }
    }
}
