<?php

namespace App\Services\Concerns;

use App\Models\SystemLog;

trait AuditsSystem
{
    /**
     * Log API access events.
     *
     * @param  string  $action  API action (api_login_success, api_login_failed,
     *                          api_bulk_import)
     * @param  array  $data  API access data
     */
    public function logApiAccessEvent(string $action, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'api_login_failed' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $data['user_id'] ?? auth()->id(),
                'entity_type' => 'ApiAccess',
                'entity_id' => $data['entity_id'] ?? null,
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log cross-branch access events.
     *
     * @param  int  $accessedBranchId  Branch ID being accessed
     * @param  string  $resource  Resource type accessed
     * @param  int  $resourceId  Resource ID accessed
     * @param  array  $data  Access data
     */
    public function logBranchAccessEvent(int $accessedBranchId, string $resource, int $resourceId, array $data = []): SystemLog
    {
        return $this->logWithSeverity(
            'cross_branch_access',
            [
                'user_id' => auth()->id(),
                'entity_type' => $resource,
                'entity_id' => $resourceId,
                'new_values' => [
                    'accessed_branch_id' => $accessedBranchId,
                    'accessed_branch_name' => $data['branch_name'] ?? null,
                    'user_branch_id' => auth()->user()?->branch_id ?? null,
                ],
            ],
            'WARNING'
        );
    }

    /**
     * Log batch operation events.
     *
     * @param  string  $action  Batch action (batch_import_completed,
     *                          batch_approval_completed)
     * @param  array  $data  Batch operation data
     */
    public function logBatchOperationEvent(string $action, array $data = []): SystemLog
    {
        return $this->logWithSeverity(
            $action,
            [
                'user_id' => auth()->id(),
                'entity_type' => 'BatchOperation',
                'entity_id' => $data['batch_id'] ?? null,
                'new_values' => [
                    'items_processed' => $data['items_processed'] ?? 0,
                    'items_succeeded' => $data['items_succeeded'] ?? 0,
                    'items_failed' => $data['items_failed'] ?? 0,
                ],
            ],
            'INFO'
        );
    }

    public function logProcedureTrigger(string $procedureName, array $parameters = []): SystemLog
    {
        return $this->logWithSeverity(
            'procedure_triggered',
            [
                'entity_type' => 'Procedure',
                'entity_id' => null,
                'new_values' => [
                    'procedure_name' => $procedureName,
                    'parameters' => $parameters,
                ],
            ],
            'INFO'
        );
    }

    public function logControllerAction(
        string $controller,
        string $action,
        ?int $userId,
        array $requestData = [],
        array $result = []
    ): SystemLog {
        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $userId,
                'entity_type' => $controller,
                'new_values' => [
                    'request_data' => $requestData,
                    'result' => $result,
                ],
            ],
            'INFO'
        );
    }

    public function logModelEvent(
        string $model,
        string $event,
        ?int $modelId,
        array $changes = [],
        array $original = []
    ): SystemLog {
        return $this->logWithSeverity(
            strtoupper($event),
            [
                'entity_type' => $model,
                'entity_id' => $modelId,
                'old_values' => $original,
                'new_values' => $changes,
            ],
            'INFO'
        );
    }
}
