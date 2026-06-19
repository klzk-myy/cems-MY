<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SanctionList\IndexSanctionEntryRequest;
use App\Http\Requests\Api\V1\SanctionList\StoreSanctionEntryRequest;
use App\Http\Requests\Api\V1\SanctionList\UpdateSanctionEntryRequest;
use App\Models\SanctionEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Services\Compliance\SanctionsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SanctionListController extends Controller
{
    public function __construct(
        protected SanctionsImportService $importService,
    ) {}

    public function lists(): JsonResponse
    {
        $lists = SanctionList::withCount('entries')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lists->map(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'source_url' => $list->source_url,
                'source_format' => $list->source_format,
                'update_frequency' => $list->update_frequency,
                'last_synced_at' => $list->last_updated_at?->toIso8601String(),
                'status' => $list->update_status,
                'entries_count' => $list->entries_count,
            ])->toArray(),
        ]);
    }

    public function entries(IndexSanctionEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $perPage = $validated['per_page'] ?? 50;
        $status = $validated['status'] ?? 'active';

        $query = SanctionEntry::with('sanctionList')
            ->when($validated['list_id'] ?? null, fn ($q, $id) => $q->where('list_id', $id))
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where('entity_name', 'like', "%{$search}%"))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('entity_name');

        $entries = $query->paginate($perPage);

        return response()->json([
            'data' => $entries->map(fn ($entry) => [
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
            ]),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    public function triggerImport(Request $request, int $listId): JsonResponse
    {
        $list = SanctionList::findOrFail($listId);

        try {
            $result = $this->importService->import($list, manual: true);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'success',
                    'records_added' => $result['added'],
                    'records_updated' => $result['updated'],
                    'records_deactivated' => $result['deactivated'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function importLogs(): JsonResponse
    {
        $logs = SanctionImportLog::with('sanctionList')
            ->orderBy('imported_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn ($log) => [
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
            ])->toArray(),
        ]);
    }

    public function storeEntry(StoreSanctionEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $entry = SanctionEntry::create([
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

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $entry->id,
                'entity_name' => $entry->entity_name,
            ],
        ], 201);
    }

    public function updateEntry(UpdateSanctionEntryRequest $request, int $entryId): JsonResponse
    {
        $entry = SanctionEntry::findOrFail($entryId);

        $validated = $request->validated();

        if (isset($validated['entity_name'])) {
            $validated['normalized_name'] = strtolower(preg_replace('/[^\p{L}\s]/u', '', $validated['entity_name']));
            $validated['soundex_code'] = soundex($validated['entity_name']);
            $validated['metaphone_code'] = metaphone($validated['entity_name']);
        }

        $entry->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $entry->id,
                'entity_name' => $entry->entity_name,
                'status' => $entry->status,
            ],
        ]);
    }

    public function deleteEntry(int $entryId): JsonResponse
    {
        $entry = SanctionEntry::findOrFail($entryId);

        $entry->update(['status' => 'inactive']);

        return response()->json(['success' => true, 'data' => ['message' => 'Entry deactivated']]);
    }
}
