<?php

namespace App\Http\Middleware;

use App\Models\DataBreachAlert;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DataBreachDetection
{
    protected int $threshold = 1000; // records per minute

    protected int $timeWindow = 60; // seconds

    public function handle(Request $request, Closure $next)
    {
        $userId = auth()->id();
        $ipAddress = $request->ip();
        $cacheKey = "data_access:{$userId}:{$ipAddress}";

        // Track record access
        $accessCount = Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $accessCount, $this->timeWindow);

        // Check threshold
        if ($accessCount > $this->threshold) {
            $this->triggerBreachAlert($userId, $ipAddress, $accessCount);
        }

        // Check for mass export
        if ($this->isMassExport($request)) {
            $this->triggerBreachAlert($userId, $ipAddress, 0, 'Export_Anomaly');
        }

        return $next($request);
    }

    protected function isMassExport(Request $request): bool
    {
        // Check if request is exporting large dataset
        if ($request->has('export') && $request->has('limit')) {
            return $request->input('limit') > 500;
        }

        return false;
    }

    protected function triggerBreachAlert(
        ?int $userId,
        string $ipAddress,
        int $recordCount,
        string $type = 'Mass_Access'
    ): void {
        // Check if alert already exists for this incident
        $existing = DataBreachAlert::where('triggered_by', $userId)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->where('is_resolved', false)
            ->exists();

        if ($existing) {
            return;
        }

        DataBreachAlert::create([
            'alert_type' => $type,
            'severity' => 'Critical',
            'description' => "Potential data breach: {$recordCount} PII records accessed in 1 minute",
            'record_count' => $recordCount,
            'triggered_by' => $userId,
            'ip_address' => $ipAddress,
            'is_resolved' => false,
        ]);

        $this->sendAdminNotification($userId, $ipAddress, $recordCount, $type);
    }

    protected function sendAdminNotification(?int $userId, string $ipAddress, int $recordCount, string $type): void
    {
        try {
            $adminEmails = User::where('role', 'admin')
                ->where('is_active', true)
                ->pluck('email')
                ->toArray();

            if (empty($adminEmails)) {
                Log::warning('No active admin users found for data breach notification');

                return;
            }

            foreach ($adminEmails as $email) {
                Mail::raw(
                    "Data Breach Alert\n\n".
                    "Type: {$type}\n".
                    "User ID: {$userId}\n".
                    "IP Address: {$ipAddress}\n".
                    "Record Count: {$recordCount}\n".
                    'Timestamp: '.now()->toIso8601String()."\n\n".
                    'Please investigate immediately.',
                    function ($message) use ($email, $type) {
                        $message->to($email)
                            ->subject("CRITICAL: Data Breach Alert - {$type}");
                    }
                );
            }

            Log::info('Data breach notification sent to admins', [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'type' => $type,
                'admin_count' => count($adminEmails),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send data breach notification', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip_address' => $ipAddress,
            ]);
        }
    }
}
