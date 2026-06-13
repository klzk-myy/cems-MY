<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\SanctionEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Services\SanctionsOrchestrationService;
use Illuminate\Http\Request;

class SanctionListController extends Controller
{
    public function __construct(
        protected SanctionsOrchestrationService $orchestrationService,
    ) {}

    public function index()
    {
        $lists = SanctionList::withCount('entries')
            ->orderBy('name')
            ->get()
            ->map(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'source_url' => $list->source_url,
                'source_format' => $list->source_format,
                'update_frequency' => $list->update_frequency,
                'last_synced_at' => $list->last_updated_at?->toIso8601String(),
                'status' => $list->update_status,
                'entries_count' => $list->entries_count,
            ]);

        return view('compliance.sanctions.index', compact('lists'));
    }

    public function show(int $id)
    {
        $list = SanctionList::find($id);

        if (! $list) {
            return redirect()->route('compliance.sanctions.index')
                ->with('error', 'Sanction list not found');
        }

        $listData = [
            'id' => $list->id,
            'name' => $list->name,
            'source_url' => $list->source_url,
            'source_format' => $list->source_format,
            'update_frequency' => $list->update_frequency,
            'last_synced_at' => $list->last_updated_at?->toIso8601String(),
            'status' => $list->update_status,
            'entries_count' => $list->entries_count,
        ];

        return view('compliance.sanctions.show', compact('list'));
    }

    public function entriesIndex(Request $request)
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

    public function showEntry(int $id)
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

        return view('compliance.sanctions.entries.show', compact('entry'));
    }

    public function createEntry()
    {
        $lists = SanctionList::orderBy('name')->get(['id', 'name']);

        return view('compliance.sanctions.entries.create', compact('lists'));
    }

    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
            'list_id' => 'required|integer|exists:sanction_lists,id',
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'required|in:Individual,Organization,Vessel,Aircraft',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|string',
        ]);

        $validated['aliases'] = $request->has('aliases')
            ? array_filter(array_map('trim', explode("\n", $request->input('aliases'))))
            : null;

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
            'normalized_name' => strtolower(preg_replace('/[^\p{L}\s]/u', '', $validated['entity_name'])),
            'soundex_code' => soundex($validated['entity_name']),
            'metaphone_code' => metaphone($validated['entity_name']),
            'status' => 'active',
        ]);

        return redirect()->route('compliance.sanctions.entries.index')
            ->with('success', 'Sanction entry created successfully');
    }

    public function editEntry(SanctionEntry $entry)
    {
        return view('compliance.sanctions.entries.edit', ['sanctionEntry' => $entry]);
    }

    public function updateEntry(Request $request, SanctionEntry $entry)
    {
        $request->merge([
            'entity_type' => ucfirst($request->input('entity_type', '')),
        ]);

        $validated = $request->validate([
            'entity_name' => 'required|string|max:255',
            'list_source' => 'nullable|string|max:255',
            'entity_type' => 'required|in:Individual,Organization,Vessel,Aircraft',
            'reference_number' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'date_listed' => 'nullable|date',
            'aliases' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'details' => 'nullable|string',
        ]);

        $validated['aliases'] = $request->has('aliases')
            ? array_filter(array_map('trim', explode("\n", $request->input('aliases'))))
            : null;

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

        $updateData['normalized_name'] = strtolower(preg_replace('/[^\p{L}\s]/u', '', $validated['entity_name']));
        $updateData['soundex_code'] = soundex($validated['entity_name']);
        $updateData['metaphone_code'] = metaphone($validated['entity_name']);

        $entry->update($updateData);

        return redirect()->route('compliance.sanctions.entries.show', $entry)
            ->with('success', 'Sanction entry updated successfully');
    }

    public function importLogs()
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

    public function triggerImport(int $listId)
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
