<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\SystemAlert;
use App\Services\AuditService;
use App\Services\System\CacheInvalidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin-only system alert management.
 *
 * Surfaces SystemAlert records (monitoring alerts, daily summaries, health
 * checks) so admins can review and acknowledge them. Acknowledging records who
 * cleared the alert and when, and writes an audit trail entry.
 */
class SystemAlertController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected CacheInvalidationService $cacheInvalidationService,
    ) {}

    /**
     * List system alerts with optional level and status filters.
     */
    public function index(Request $request): View
    {
        $this->requirePermission(Permission::ManageSystemAlerts);

        $query = SystemAlert::with('acknowledgedBy')->latest();

        if (in_array($request->query('level'), ['info', 'warning', 'critical'], true)) {
            $query->where('level', $request->query('level'));
        }

        if ($request->query('status') === 'unacknowledged') {
            $query->unacknowledged();
        } elseif ($request->query('status') === 'acknowledged') {
            $query->acknowledged();
        }

        $alerts = $query->paginate(25)->withQueryString();

        return view('system.alerts.index', compact('alerts'));
    }

    /**
     * Landing page for acknowledge links (e.g. from alert emails).
     *
     * The state change itself is POST-only; this page renders a CSRF-protected
     * confirmation form so a plain GET can never mutate the alert.
     */
    public function showAcknowledge(SystemAlert $alert): View
    {
        $this->requirePermission(Permission::ManageSystemAlerts);

        return view('system.alerts.acknowledge-confirm', [
            'alert' => $alert,
        ]);
    }

    /**
     * Acknowledge an alert.
     *
     * Idempotent: acknowledging an already-acknowledged alert is a no-op that
     * just informs the caller. Internal exception details stay out of the UI.
     */
    public function acknowledge(SystemAlert $alert): RedirectResponse
    {
        $this->requirePermission(Permission::ManageSystemAlerts);

        if ($alert->isAcknowledged()) {
            return back(status: 302, fallback: route('system.alerts.index'))->with('info', 'Alert was already acknowledged.');
        }

        try {
            $alert->acknowledge((int) auth()->id());

            // The dashboard monitoring widget caches alert counts/recent alerts
            // for 60s; drop it now so the acknowledged alert disappears from the
            // dashboard immediately instead of lingering as stale.
            $this->cacheInvalidationService->invalidate('dashboard');

            $this->auditService->logWithSeverity(
                'system_alert_acknowledged',
                [
                    'entity_type' => 'SystemAlert',
                    'entity_id' => $alert->id,
                    'user_id' => auth()->id(),
                    'new_values' => [
                        'level' => $alert->level->value,
                        'message' => $alert->message,
                    ],
                ],
                'INFO'
            );
        } catch (\Exception $e) {
            Log::error('Failed to acknowledge system alert', [
                'alert_id' => $alert->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back(status: 302, fallback: route('system.alerts.index'))->with('error', 'Unable to acknowledge the alert. Check the application logs for details.');
        }

        return back(status: 302, fallback: route('system.alerts.index'))->with('success', 'Alert acknowledged.');
    }
}
