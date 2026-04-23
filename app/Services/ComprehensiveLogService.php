<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Comprehensive Logging Service
 *
 * Captures all business activities with timestamps for audit and debugging purposes.
 */
class ComprehensiveLogService
{
    protected string $logFile;

    protected bool $useDatabase;

    public function __construct()
    {
        $this->logFile = storage_path('logs/business-activity.log');
        $this->useDatabase = true; // Also log to database for persistence
    }

    /**
     * Log a business activity with timestamp
     */
    public function log(
        string $category,
        string $action,
        string $entity,
        ?int $entityId,
        array $data = [],
        string $status = 'INFO'
    ): void {
        $timestamp = now()->toIso8601String();
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // Format log message
        $message = $this->formatMessage($category, $action, $entity, $entityId, $data, $status);

        // Write to file
        $this->writeToFile($message);

        // Write to database
        if ($this->useDatabase) {
            $this->writeToDatabase($category, $action, $entity, $entityId, $data, $status, $timestamp);
        }

        // Also log to Laravel log
        $this->logToLaravel($message, $status);
    }

    /**
     * Log with verbose details including stack trace
     */
    public function logVerbose(
        string $category,
        string $action,
        string $entity,
        ?int $entityId,
        array $data = [],
        string $status = 'INFO',
        int $stackDepth = 3
    ): void {
        $timestamp = now()->toIso8601String();

        // Get caller information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $stackDepth);
        $caller = end($backtrace);
        $callerInfo = isset($caller['file']) ? "{$caller['file']}:{$caller['line']}" : 'unknown';

        $message = sprintf(
            "[%s] [%s] [%s] %s::%s Entity: %s (#s) Caller: %s\nData: %s\n",
            $timestamp,
            $status,
            $category,
            $action,
            $entity,
            $entityId ?? 'N/A',
            $callerInfo,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Write to specific verbose log file
        $verboseFile = storage_path('logs/business-activity-verbose.log');
        file_put_contents($verboseFile, $message."\n", FILE_APPEND);

        // Also call regular log
        $this->log($category, $action, $entity, $entityId, $data, $status);
    }

    /**
     * Log procedure trigger
     */
    public function logProcedureTrigger(string $procedureName, array $parameters = []): void
    {
        $this->logVerbose(
            'PROCEDURE',
            'TRIGGERED',
            $procedureName,
            null,
            ['parameters' => $parameters],
            'INFO'
        );
    }

    /**
     * Log controller action
     */
    public function logControllerAction(
        string $controller,
        string $action,
        ?int $userId,
        array $requestData = [],
        array $result = []
    ): void {
        $this->logVerbose(
            'CONTROLLER',
            $action,
            $controller,
            $userId,
            [
                'request_data' => $requestData,
                'result' => $result,
            ],
            'INFO'
        );
    }

    /**
     * Log model event
     */
    public function logModelEvent(
        string $model,
        string $event,
        ?int $modelId,
        array $changes = [],
        array $original = []
    ): void {
        $this->logVerbose(
            'MODEL',
            strtoupper($event),
            $model,
            $modelId,
            [
                'changes' => $changes,
                'original' => $original,
            ],
            'INFO'
        );
    }

    /**
     * Log transaction workflow
     */
    public function logTransactionWorkflow(
        string $step,
        int $transactionId,
        string $status,
        array $context = []
    ): void {
        $this->logVerbose(
            'TRANSACTION_WORKFLOW',
            $step,
            'Transaction',
            $transactionId,
            $context,
            $status
        );
    }

    /**
     * Log error with full context
     */
    public function logError(
        string $category,
        \Throwable $exception,
        array $context = []
    ): void {
        $errorData = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'previous' => $exception->getPrevious() ? $exception->getPrevious()->getMessage() : null,
        ] + $context;

        $this->logVerbose(
            $category,
            'ERROR',
            get_class($exception),
            null,
            $errorData,
            'ERROR'
        );
    }

    /**
     * Format log message
     */
    protected function formatMessage(
        string $category,
        string $action,
        string $entity,
        ?int $entityId,
        array $data,
        string $status
    ): string {
        $timestamp = now()->toIso8601String();
        $dataStr = ! empty($data) ? ' | '.json_encode($data, JSON_UNESCAPED_UNICODE) : '';

        return sprintf(
            '[%s] [%s] [%s::%s] %s(#%s)%s',
            $timestamp,
            $status,
            $category,
            $action,
            $entity,
            $entityId ?? 'N/A',
            $dataStr
        );
    }

    /**
     * Write to file
     */
    protected function writeToFile(string $message): void
    {
        // Ensure log directory exists
        if (! is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }

        file_put_contents($this->logFile, $message.PHP_EOL, FILE_APPEND);
    }

    /**
     * Write to database
     */
    protected function writeToDatabase(
        string $category,
        string $action,
        string $entity,
        ?int $entityId,
        array $data,
        string $status,
        string $timestamp
    ): void {
        try {
            DB::table('business_activity_logs')->insert([
                'timestamp' => $timestamp,
                'category' => $category,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'data' => ! empty($data) ? json_encode($data) : null,
                'status' => $status,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail if table doesn't exist yet
        }
    }

    /**
     * Log to Laravel log
     */
    protected function logToLaravel(string $message, string $status): void
    {
        if ($status === 'ERROR') {
            Log::error($message);
        } elseif ($status === 'WARNING') {
            Log::warning($message);
        } else {
            Log::info($message);
        }
    }

    /**
     * Get log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Clear log file
     */
    public function clearLog(): void
    {
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs(int $limit = 100): array
    {
        if (! file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return array_slice(array_reverse($lines), 0, $limit);
    }
}
