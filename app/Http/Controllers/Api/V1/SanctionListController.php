<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\DomainException;
use App\Http\Concerns\SanctionEntryNormalizer;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SanctionList\IndexSanctionEntryRequest;
use App\Http\Requests\Api\V1\SanctionList\StoreSanctionEntryRequest;
use App\Http\Requests\Api\V1\SanctionList\UpdateSanctionEntryRequest;
use App\Models\SanctionEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Services\Compliance\SanctionsImportService;
use App\Support\LikeEscaper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SanctionListController extends Controller
{
    use ApiResponse;
    use SanctionEntryNormalizer;

    public function __construct(
        protected SanctionsImportService $importService,
    ) {}

    public function lists(): JsonResponse
    {
        $lists = SanctionList::withCount('entries')
            ->orderBy('name')
            ->get();

        return $this->successResponse($lists->map(fn ($list) => [
            'id' => $list->id,
            'name' => $list->name,
            'source_url' => $list->source_url,
            'source_format' => $list->source_format,
            'update_frequency' => $list->update_status,
            'last_synced_at' => $list->last_updated_at?->toIso8601String(),
            'status' => $list->update_status,
            'entries_count' => $list->entries_count,
        ])->toArray());
    }

    public function entries(IndexSanctionEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $perPage = $validated['per_page'] ?? 50;
        $status = $validated['status'] ?? 'active';

        $query = SanctionEntry::with('sanctionList')
            ->when($validated['list_id'] ?? null, fn ($q, $id) => $q->where('list_id', $id))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $pattern = '%'.LikeEscaper::escape($search).'%';

                return $q->whereRaw('entity_name LIKE ? ESCAPE ?', [$pattern, '\\']);
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('entity_name');

        $entries = $query->paginate($perPage);

        // Legacy non-standard envelope (data/meta); preserved to avoid breaking API consumers.
        return response()->json([
            'data' => $entries->map(fn ($entry) => collect($entry->toEntrySummaryArray())
                ->except('list_source')
                ->toArray()),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    public function triggerImport(int $listId): JsonResponse
    {
        $list = SanctionList::findOrFail($listId);

        try {
            $result = $this->importService->import($list, manual: true);

            return $this->successResponse([
                'status' => 'success',
                'records_added' => $result['created'],
                'records_updated' => $result['updated'],
                'records_deactivated' => $result['deactivated'],
            ]);
        } catch (DomainException $e) {
            return $this->errorResponse('Import failed', ['code' => $e->getErrorCode()], $e->getStatusCode(), [
                'data' => [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Sanctions import failed', ['error' => $e->getMessage(), 'list_id' => $listId]);

            return $this->errorResponse('Import failed', [], 500, [
                'data' => [
                    'status' => 'failed',
                    'error' => 'Import failed. Check server logs for details.',
                ],
            ]);
        }
    }

    public function importLogs(): JsonResponse
    {
        $logs = SanctionImportLog::with('sanctionList')
            ->orderBy('imported_at', 'desc')
            ->limit(50)
            ->get();

        return $this->successResponse($logs->map(fn ($log) => $log->toSummaryArray())->toArray());
    }

    public function storeEntry(StoreSanctionEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $normalized = $this->normalizeEntityName($validated['entity_name']);

        $entry = SanctionEntry::create(SanctionEntry::buildForCreate($validated, $normalized));

        return $this->successResponse([
            'id' => $entry->id,
            'entity_name' => $entry->entity_name,
        ], 'Entry created successfully.', 201);
    }

    public function updateEntry(UpdateSanctionEntryRequest $request, int $entryId): JsonResponse
    {
        $entry = SanctionEntry::findOrFail($entryId);

        $validated = $request->validated();

        if (isset($validated['entity_name'])) {
            $normalized = $this->normalizeEntityName($validated['entity_name']);
        } else {
            $normalized = [
                'normalized_name' => $entry->normalized_name,
                'soundex_code' => $entry->soundex_code,
                'metaphone_code' => $entry->metaphone_code,
            ];
        }

        $entry->update(SanctionEntry::buildForUpdate($validated, $normalized));

        return $this->successResponse([
            'id' => $entry->id,
            'entity_name' => $entry->entity_name,
            'status' => $entry->status,
        ], 'Entry updated successfully.');
    }

    public function deleteEntry(int $entryId): JsonResponse
    {
        $entry = SanctionEntry::findOrFail($entryId);

        $entry->update(['status' => 'inactive']);

        return $this->successResponse(['message' => 'Entry deactivated'], 'Entry deactivated');
    }
}
