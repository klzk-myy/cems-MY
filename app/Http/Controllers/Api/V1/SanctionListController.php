<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SanctionStatus;
use App\Exceptions\Domain\DomainException;
use App\Http\Concerns\SanctionEntryNormalizer;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Api\V1\Traits\LegacyApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SanctionList\IndexSanctionEntryRequest;
use App\Http\Requests\Api\V1\SanctionList\StoreSanctionEntryRequest;
use App\Http\Requests\Api\V1\SanctionList\UpdateSanctionEntryRequest;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\SanctionImportLog;
use App\Models\Compliance\SanctionList;
use App\Services\Compliance\SanctionsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SanctionListController extends Controller
{
    use ApiResponse;
    use LegacyApiResponse;
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
            'source_format' => $list->source_format?->value,
            'update_frequency' => $list->update_status->value,
            'last_synced_at' => $list->last_updated_at?->toIso8601String(),
            'status' => $list->update_status->value,
            'entries_count' => $list->entries_count,
        ])->toArray());
    }

    public function entries(IndexSanctionEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $perPage = $validated['per_page'] ?? 20;
        $status = $validated['status'] ?? SanctionStatus::Active->value;

        $query = SanctionEntry::filtered(
            $status,
            isset($validated['list_id']) ? (int) $validated['list_id'] : null,
            $validated['search'] ?? null
        );

        $entries = $query->paginate($perPage);

        // Legacy non-standard envelope (data/meta); preserved to avoid breaking API consumers.
        return $this->legacyJsonResponse([
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
        $logs = SanctionImportLog::with(['sanctionList', 'user'])
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

        $entry->update(['status' => SanctionStatus::Inactive->value]);

        return $this->successResponse(['message' => 'Entry deactivated'], 'Entry deactivated');
    }
}
