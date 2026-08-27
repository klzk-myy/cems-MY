<?php

namespace App\Http\Controllers;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\EmergencyCloseCooldownException;
use App\Exceptions\Domain\EmergencyCloseSessionTooNewException;
use App\Http\Requests\AcknowledgeHandoverWebRequest;
use App\Http\Requests\CloseCounterRequest;
use App\Http\Requests\EmergencyCloseRequest;
use App\Http\Requests\HandoverCounterRequest;
use App\Http\Requests\OpenCounterRequest;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\EmergencyClosure;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Branch\CounterHandoverService;
use App\Services\Branch\CounterService;
use App\Services\Branch\EmergencyCounterService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CounterController extends Controller
{
    public function __construct(
        protected CounterService $counterService,
        protected AuditService $auditService,
        protected EmergencyCounterService $emergencyCounterService,
        protected CounterHandoverService $counterHandoverService,
    ) {}

    /**
     * Display a listing of counters
     */
    public function index(): View
    {
        $today = now()->toDateString();
        $counters = Counter::with(['sessions' => function ($query) use ($today) {
            $query->whereDate('session_date', $today)
                ->where('status', CounterSessionStatus::Open->value);
        }])->get();

        $stats = [
            'total' => $counters->count(),
            'open' => $counters->filter(fn ($c) => $c->sessions->count() > 0)->count(),
            'available' => $counters->filter(fn ($c) => $c->sessions->count() === 0)->count(),
        ];

        $availableCounters = $this->counterService->getAvailableCounters();
        $currencies = $this->getActiveCurrencies();

        return view('counters.index', compact('counters', 'stats', 'availableCounters', 'currencies'));
    }

    /**
     * Show the form for opening a counter
     */
    public function showOpen(Counter $counter): View
    {
        $this->ensureCounterBranchAccess($counter);

        $availableCounters = $this->counterService->getAvailableCounters();
        $currencies = $this->getActiveCurrencies();

        return view('counters.open', compact('counter', 'availableCounters', 'currencies'));
    }

    /**
     * Open a counter session
     */
    public function open(OpenCounterRequest $request, Counter $counter): RedirectResponse
    {
        $this->ensureCounterBranchAccess($counter);

        /** @var User $user */
        $user = auth()->user();
        $openingFloats = $request->input('opening_floats');
        $today = now()->toDateString();

        return $this->handleCounterAction(
            action: 'counter_opened',
            operation: fn () => $this->counterService->openSession($counter, $user, $openingFloats),
            successMessage: "Counter {$counter->code} opened successfully",
            redirectRoute: 'counters.index',
            auditContext: [
                'user_id' => $user->id,
                'auditable_type' => 'CounterSession',
                'counter_id' => $counter->id,
                'new_values' => [
                    'counter_code' => $counter->code,
                    'counter_name' => $counter->name,
                    'opened_by' => $user->username,
                    'session_date' => $today,
                    'opening_floats' => $openingFloats,
                ],
            ]
        );
    }

    public function showClose(Counter $counter): View
    {
        $this->ensureCounterBranchAccess($counter);

        $today = now()->toDateString();
        $session = $this->findOpenSession($counter, $today);

        if (! $session) {
            abort(404, 'No open session found for this counter today.');
        }

        $currencies = $this->getActiveCurrencies();

        return view('counters.close', compact('counter', 'session', 'currencies'));
    }

    /**
     * Close a counter session
     */
    public function close(CloseCounterRequest $request, Counter $counter): RedirectResponse
    {
        $this->ensureCounterBranchAccess($counter);

        /** @var User $user */
        $user = auth()->user();
        $closingFloats = $request->input('closing_floats');
        $notes = $request->input('notes');
        $today = now()->toDateString();

        $session = $this->findOpenSession($counter, $today);

        if (! $session) {
            return back()->with('error', 'No open session found for this counter today.');
        }

        return $this->handleCounterAction(
            action: 'counter_closed',
            operation: fn () => $this->counterService->closeSession($session, $user, $closingFloats, $notes),
            successMessage: "Counter {$counter->code} closed successfully",
            redirectRoute: 'counters.index',
            auditContext: [
                'user_id' => $user->id,
                'auditable_type' => 'CounterSession',
                'auditable_id' => $session->id,
                'counter_id' => $counter->id,
                'old_values' => [
                    'counter_code' => $counter->code,
                    'status' => CounterSessionStatus::Open->value,
                ],
                'new_values' => [
                    'counter_code' => $counter->code,
                    'status' => CounterSessionStatus::Closed->value,
                    'closed_by' => $user->username,
                    'closing_floats' => $closingFloats,
                    'notes' => $notes,
                ],
            ]
        );
    }

    public function status(Counter $counter): JsonResponse
    {
        $this->ensureCounterBranchAccess($counter);

        $status = $this->counterService->getCounterStatus($counter);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function history(Request $request, Counter $counter): View
    {
        $this->ensureCounterBranchAccess($counter);

        $query = CounterSession::where('counter_id', $counter->id)
            ->with(['user', 'openedByUser', 'closedByUser']);

        if ($request->has('from_date')) {
            $query->where('session_date', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('session_date', '<=', $request->input('to_date'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $sessions = $query->orderBy('session_date', 'desc')
            ->orderBy('opened_at', 'desc')
            ->paginate(20)->withQueryString();

        $users = User::select('id', 'username', 'role')->where('is_active', true)->get();

        return view('counters.history', compact('counter', 'sessions', 'users'));
    }

    public function showHandover(Counter $counter): View
    {
        $this->ensureCounterBranchAccess($counter);

        $today = now()->toDateString();
        $session = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', $today)
            ->where('status', CounterSessionStatus::Open->value)
            ->first();

        if (! $session) {
            abort(404, 'No open session found for this counter today.');
        }

        $availableUsers = User::select('id', 'username', 'role')
            ->where('is_active', true)
            ->where('id', '!=', auth()->id())
            ->get();

        $supervisors = User::select('id', 'username', 'role')
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Manager, UserRole::Admin])
            ->get();

        $currencies = $this->getActiveCurrencies();

        return view('counters.handover', compact('counter', 'session', 'availableUsers', 'supervisors', 'currencies'));
    }

    public function handover(HandoverCounterRequest $request, Counter $counter): RedirectResponse
    {
        $this->ensureCounterBranchAccess($counter);

        /** @var User|null $fromUser */
        $fromUser = User::find($request->input('from_user_id'));
        if (! ($fromUser instanceof User)) {
            return back()->with('error', 'From user not found.');
        }
        $today = now()->toDateString();

        $session = $this->findOpenSession($counter, $today, $fromUser);

        if (! $session) {
            return back()->with('error', 'No open session found for this counter and user today.');
        }

        /** @var User|null $toUser */
        $toUser = User::find($request->input('to_user_id'));
        if (! ($toUser instanceof User)) {
            return back()->with('error', 'To user not found.');
        }
        /** @var User|null $supervisor */
        $supervisor = User::find($request->input('supervisor_id'));
        if (! ($supervisor instanceof User)) {
            return back()->with('error', 'Supervisor not found.');
        }

        // Enforce same-branch membership for all involved users
        $expectedBranchId = $counter->branch_id;
        if ($fromUser->branch_id !== $expectedBranchId || $toUser->branch_id !== $expectedBranchId || $supervisor->branch_id !== $expectedBranchId) {
            return back()->with('error', 'All users must belong to the counter branch.');
        }

        $physicalCounts = $request->input('physical_counts');

        return $this->handleCounterAction(
            action: 'counter_handed_over',
            operation: fn () => $this->counterService->initiateHandover(
                $session,
                $fromUser,
                $toUser,
                $supervisor,
                $physicalCounts
            ),
            successMessage: "Counter {$counter->code} handed over to {$toUser->username}",
            redirectRoute: 'counters.index',
            auditContext: [
                'user_id' => $fromUser->id,
                'auditable_type' => 'CounterSession',
                'auditable_id' => $session->id,
                'counter_id' => $counter->id,
                'old_values' => [
                    'counter_code' => $counter->code,
                    'from_user' => $fromUser->username,
                    'status' => CounterSessionStatus::Open->value,
                ],
                'new_values' => [
                    'counter_code' => $counter->code,
                    'from_user' => $fromUser->username,
                    'to_user' => $toUser->username,
                    'supervisor' => $supervisor->username,
                    'status' => CounterSessionStatus::HandedOver->value,
                    'physical_counts' => $physicalCounts,
                ],
            ]
        );
    }

    public function showEmergency(Counter $counter): View
    {
        $this->ensureCounterBranchAccess($counter);

        $today = now()->toDateString();
        $session = $this->findOpenSession($counter, $today);

        if (! $session || ! $session->isOpen()) {
            abort(400, 'Counter does not have an active session');
        }

        return view('counters.emergency', compact('counter', 'session'));
    }

    public function emergency(EmergencyCloseRequest $request, Counter $counter): RedirectResponse
    {
        $this->ensureCounterBranchAccess($counter);

        /** @var User $user */
        $user = auth()->user();

        try {
            $closure = $this->emergencyCounterService->initiateEmergencyClose(
                $counter,
                $user,
                $request->input('reason')
            );

            return redirect()->route('counters.index')
                ->with('success', "Emergency closure initiated for counter {$counter->code}. A manager has been notified.");
        } catch (EmergencyCloseCooldownException $e) {
            return back()->with('error', 'An emergency closure was recently initiated for this counter. Please wait before initiating another.');
        } catch (EmergencyCloseSessionTooNewException $e) {
            return back()->with('error', 'The counter session is too new to be closed in emergency mode.');
        }
    }

    public function showEmergencyClosure(Counter $counter, EmergencyClosure $closure): View
    {
        $this->ensureCounterBranchAccess($counter);

        if ($closure->counter_id !== $counter->id) {
            abort(404);
        }

        $variance = $this->emergencyCounterService->getVariance($closure);

        return view('counters.emergency-closure', compact('counter', 'closure', 'variance'));
    }

    public function showAcknowledgeHandover(Counter $counter): View|RedirectResponse
    {
        $this->ensureCounterBranchAccess($counter);

        /** @var User $user */
        $user = auth()->user();
        $today = now()->toDateString();

        $handover = $this->counterHandoverService->findPendingHandover(
            $user->id,
            $counter->id,
            $today
        );

        if (! $handover) {
            return redirect()->route('counters.index')
                ->with('error', 'No pending handover to acknowledge');
        }

        return view('counters.acknowledge-handover', compact('counter', 'handover'));
    }

    public function acknowledgeHandover(AcknowledgeHandoverWebRequest $request, Counter $counter): RedirectResponse
    {
        $this->ensureCounterBranchAccess($counter);

        /** @var User $user */
        $user = auth()->user();
        $today = now()->toDateString();

        $handover = $this->counterHandoverService->findPendingHandover(
            $user->id,
            $counter->id,
            $today
        );

        if (! $handover) {
            return back()->with('error', 'No pending handover to acknowledge');
        }

        try {
            $this->counterHandoverService->acknowledgeHandover(
                $handover,
                $user,
                $request->boolean('verified'),
                $request->input('notes')
            );

            return redirect()->route('counters.index')
                ->with('success', 'Handover acknowledged successfully');
        } catch (\Exception $e) {
            Log::error('Failed to acknowledge counter handover', [
                'handover_id' => $handover->id ?? null,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to acknowledge handover. Please try again.');
        }
    }

    private function handleCounterAction(
        string $action,
        callable $operation,
        string $successMessage,
        string $redirectRoute,
        array $auditContext
    ): RedirectResponse {
        $verb = match ($action) {
            'counter_opened' => 'open',
            'counter_closed' => 'close',
            'counter_handed_over' => 'handover',
            default => $action,
        };

        try {
            $result = $operation();

            $auditableId = $auditContext['auditable_id'] ?? null;
            if ($auditableId === null && $result instanceof Model) {
                $auditableId = $result->getKey();
            }

            $this->auditService->logWithSeverity(
                $action,
                [
                    'user_id' => $auditContext['user_id'] ?? auth()->id(),
                    'entity_type' => $auditContext['auditable_type'] ?? 'Counter',
                    'entity_id' => $auditableId,
                    'old_values' => $auditContext['old_values'] ?? [],
                    'new_values' => $auditContext['new_values'] ?? [],
                ],
                $auditContext['severity'] ?? 'INFO'
            );

            return redirect()->route($redirectRoute)->with('success', $successMessage);
        } catch (\Exception $e) {
            Log::error("Counter {$verb} failed", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'counter_id' => $auditContext['counter_id'] ?? $auditContext['auditable_id'] ?? null,
            ]);

            return back()->with('error', "Failed to {$verb} counter. Please try again.");
        }
    }

    /**
     * Enforce branch isolation for counter operations: non-admins may only
     * interact with counters belonging to their own branch. Route middleware
     * already enforces roles; this closes the cross-branch hole.
     */
    private function ensureCounterBranchAccess(Counter $counter): void
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->role->canManageAllBranches()) {
            return;
        }

        if ($counter->branch_id !== $user->branch_id) {
            abort(403, 'You do not have access to counters in this branch.');
        }
    }

    private function getActiveCurrencies(): Collection
    {
        return Currency::select('code', 'name')->where('is_active', true)->get();
    }

    private function findOpenSession(Counter $counter, string $today, ?User $user = null): ?CounterSession
    {
        $query = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', $today)
            ->where('status', CounterSessionStatus::Open->value);

        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->first();
    }
}
