<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Sanctions\DownloadEuSanctionsList;
use App\Jobs\Sanctions\DownloadOfacSanctionsList;
use App\Jobs\Sanctions\DownloadUnSanctionsList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SanctionsWebhookController extends Controller
{
    /**
     * Webhook endpoint for receiving sanctions list update notifications.
     * Can be called by external services to trigger immediate updates.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Validate webhook token - always required when configured
        $configuredToken = config('sanctions.webhook.token', '');
        $providedToken = $request->header('X-Webhook-Token', '');

        if (! hash_equals($configuredToken, $providedToken)) {
            Log::warning('Sanctions webhook received with invalid or missing token', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Log webhook receipt
        Log::info('Sanctions webhook received', [
            'source' => $request->input('source'),
            'ip' => $request->ip(),
        ]);

        // Process source-specific update
        $source = $request->input('source');
        $dispatched = [];

        if ($source) {
            $dispatched = $this->dispatchSourceUpdate($source);
        } else {
            // Update all sources
            $dispatched = $this->dispatchAllUpdates();
        }

        return response()->json([
            'message' => 'Sanctions update jobs dispatched',
            'dispatched' => $dispatched,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Dispatch update for a specific source.
     */
    protected function dispatchSourceUpdate(string $source): array
    {
        $jobs = [
            'un' => DownloadUnSanctionsList::class,
            'ofac' => DownloadOfacSanctionsList::class,
            'eu' => DownloadEuSanctionsList::class,
        ];

        if (! isset($jobs[$source])) {
            return [
                'error' => "Unknown source: {$source}",
            ];
        }

        $jobClass = $jobs[$source];
        $jobClass::dispatch();

        Log::info("Dispatched {$source} sanctions update via webhook");

        return [$source];
    }

    /**
     * Dispatch updates for all enabled sources.
     */
    protected function dispatchAllUpdates(): array
    {
        $sources = ['un', 'ofac', 'eu'];
        $dispatched = [];

        foreach ($sources as $source) {
            $config = config("sanctions.sources.{$source}");
            if ($config && ($config['enabled'] ?? false)) {
                $result = $this->dispatchSourceUpdate($source);
                if (! isset($result['error'])) {
                    $dispatched[] = $source;
                }
            }
        }

        Log::info('Dispatched all sanctions updates via webhook', [
            'dispatched' => $dispatched,
        ]);

        return $dispatched;
    }

    /**
     * Health check endpoint for webhook status.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'sanctions-webhook',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
