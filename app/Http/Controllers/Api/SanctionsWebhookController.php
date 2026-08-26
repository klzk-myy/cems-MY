<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportSanctionsJob;
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

        // Reject if no token is configured (prevents accidental open webhooks)
        if (empty($configuredToken)) {
            Log::warning('Sanctions webhook called but no token configured', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Webhook not configured'], 401);
        }

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

        if (is_string($source) && $source !== '') {
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
        $sources = config('sanctions.sources', []);

        if (! isset($sources[$source])) {
            return [
                'error' => "Unknown or unsupported source: {$source}",
            ];
        }

        $config = $sources[$source];

        if (! $config || ! ($config['default_list'] ?? true)) {
            return [
                'error' => "Source {$source} is not configured or is disabled",
            ];
        }

        ImportSanctionsJob::dispatch(null, $source);

        Log::info("Dispatched {$source} sanctions update via webhook");

        return [$source];
    }

    protected function dispatchAllUpdates(): array
    {
        $sources = config('sanctions.sources', []);
        $dispatched = [];

        foreach (array_keys($sources) as $source) {
            $result = $this->dispatchSourceUpdate($source);
            if (! isset($result['error'])) {
                $dispatched[] = $source;
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
    public function health(Request $request): JsonResponse
    {
        $configuredToken = config('sanctions.webhook.token', '');
        $providedToken = $request->header('X-Webhook-Token', '');

        if (empty($configuredToken) || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'status' => 'ok',
            'service' => 'sanctions-webhook',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
