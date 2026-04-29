<?php

namespace App\Http\Controllers;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\EmergencyCloseCooldownException;
use App\Exceptions\Domain\EmergencyCloseSessionTooNewException;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\EmergencyClosure;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CounterHandoverService;
use App\Services\CounterService;
use App\Services\EmergencyCounterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
    public function index()
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
        $currencies = Currency::where('is_active', true)->get();

        return view('counters.index', compact('counters', 'stats', 'availableCounters', 'currencies'));
    }

    /**
     * Show the form for opening a counter
     */
    public function showOpen(Counter $counter)
    {
        $availableCounters = $this->counterService->getAvailableCounters();
        $currencies = Currency::where('is_active', true)->get();

        return view('counters.open', compact('counter', 'availableCounters', 'currencies'));
    }

    /**
     * Open a counter session
     */
    public function open(Request $request, Counter $counter)
    {
        $request->validate([
            'opening_floats' => 'required|array',
            'opening_floats.*.currency_id' => 'required|exists:currencies,code',
            'opening_floats.*.amount' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $openingFloats = $request->input('opening_floats');

        try {
            $session = $this->counterService->openSession($counter, $user, $openingFloats);

            // Audit log
            $this->auditService->logWithSeverity(
                'counter_opened',
                [
                    'user_id' => $user->id,
                    'entity_type' => 'CounterSession',
                    'entity_id' => $session->id,
                    'new_values' => [
                        'counter_code' => $counter->code,
                        'counter_name' => $counter->name,
                        'opened_by' => $user->username,
                        'session_date' => $session->session_date->toDateString(),
                        'opening_floats' => $openingFloats,
                    ],
                ],
                'INFO'
            );

            return redirect()->route('counters.index')
                ->with('success', "Counter {$counter->code} opened successfully");
        } catch (\Exception $e) {
            Log::error('Counter open failed', ['exception' => $e, 'counter_id' => $counter->id]);

            return back()->with('error', "Failed to open counter: {$e->getMessage()}");
        }
    }

    /**
     * Show the form for closing a counter
     */
    public function showClose(Counter $counter)
    {
        $today = now()->toDateString();
        $session = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', $today)
            ->where('status', CounterSessionStatus::Open->value)
            ->firstOrFail();

        $currencies = Currency::where('is_active', true)->get();

        return view('counters.close', compact('counter', 'session', 'currencies'));
    }

    /**
     * Close a counter session
     */
    public function close(Request $request, Counter $counter)
    {
        $this->requireManagerOrAdmin();

        $request->validate([
            'closing_floats' => 'required|array',
            'closing_floats.*.currency_id' => 'required|exists:currencies,code',
            'closing_floats.*.amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $user = Auth::user();
        $closingFloats = $request->input('closing_floats');
        $notes = $request->input('notes');
        $today = now()->toDateString();

        $session = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', $today)
            ->where('status', CounterSessionStatus::Open->value)
            ->firstOrFail();

        try {
            $this->counterService->closeSession($session, $user, $closingFloats, $notes);

            // Audit log
            $this->auditService->logWithSeverity(
                'counter_closed',
                [
                    'user_id' => $user->id,
                    'entity_type' => 'CounterSession',
                    'entity_id' => $session->id,
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
                ],
                'INFO'
            );

            return redirect()->route('counters.index')
                ->with('success', "Counter {$counter->code} closed successfully");
        } catch (\Exception $e) {
            Log::error('Counter close failed', ['exception' => $e, 'counter_id' => $counter->id]);

            return back()->with('error', "Failed to close counter: {$e->getMessage()}");
        }
    }

    /**
     * Get counter status via API
     */
    public function status(Counter $counter)
    {
        $status = $this->counterService->getCounterStatus($counter);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Display counter history
     */
    public function history(Request $request, Counter $counter)
    {
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
            ->paginate(20);

        $users = User::where('is_active', true)->get();

        return view('counters.history', compact('counter', 'sessions', 'users'));
    }

    /**
     * Show handover form
     */
    public function showHandover(Counter $counter)
    {
        $today = now()->toDateString();
        $session = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', $today)
            ->where('status', CounterSessionStatus::Open->value)
            ->firstOrFail();

        $availableUsers = User::where('is_active', true)
            ->where('id', '!=', Auth::id())
            ->get();

        $supervisors = User::where('is_active', true)
            ->whereIn('role', [UserRole::Manager, UserRole::Admin])
            ->get();

        $currencies = Currency::where('is_active', true)->get();

        return view('counters.handover', compact('counter', 'session', 'availableUsers', 'supervisors', 'currencies'));
    }

    /**
     * Process handover
     */
    public function handover(Request $request, Counter $counter)
    {
        $this->requireManagerOrAdmin();

        $request->validate([
            'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id',
            'supervisor_id' => 'required|exists:users,id',
            'physical_counts' => 'required|array',
            'physical_counts.*.currency_id' => 'required|exists:currencies,code',
            'physical_counts.*.amount' => 'required|numeric|min:0',
            'variance_notes' => 'nullable|string',
        ]);

        $fromUser = User::findOrFail($request->input('from_user_id'));
        $today = now()->toDateString();

        $session = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', $today)
            ->where('user_id', $fromUser->id)
            ->where('status', CounterSessionStatus::Open->value)
            ->firstOrFail();

        $toUser = User::findOrFail($request->input('to_user_id'));
        $supervisor = User::findOrFail($request->input('supervisor_id'));
        $physicalCounts = $request->input('physical_counts');

        try {
            $this->counterService->initiateHandover(
                $session,
                $fromUser,
                $toUser,
                $supervisor,
                $physicalCounts
            );

            // Audit log
            $this->auditService->logWithSeverity(
                'counter_handed_over',
                [
                    'user_id' => $fromUser->id,
                    'entity_type' => 'CounterSession',
                    'entity_id' => $session->id,
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
                ],
                'INFO'
            );

            return redirect()->route('counters.index')
                ->with('success', "Counter {$counter->code} handed over to {$toUser->name}");
        } catch (\Exception $e) {
            Log::error('Counter handover failed', ['exception' => $e, 'counter_id' => $counter->id]);

            return back()->with('error', "Failed to handover counter: {$e->getMessage()}");
        }
    }

    public function showEmergency(Counter $counter)
    {
        $session = $counter->currentSession;
        if (! $session || ! $session->isOpen()) {
            abort(400, 'Counter does not have an active session');
        }

        return view('counters.emergency', compact('counter', 'session'));
    }

    public function emergency(Request $request, Counter $counter)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = Auth::user();

        $emergencyService = app(EmergencyCounterService::class);

        try {
            $closure = $emergencyService->initiateEmergencyClose(
                $counter,
                $user,
                $request->input('reason')
            );

            return redirect()->route('counters.index')
                ->with('success', "Emergency closure initiated for counter {$counter->code}. A manager has been notified.");
        } catch (EmergencyCloseCooldownException $e) {
            return back()->with('error', $e->getMessage());
        } catch (EmergencyCloseSessionTooNewException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function showEmergencyClosure(Counter $counter, EmergencyClosure $closure)
    {
        if ($closure->counter_id !== $counter->id) {
            abort(404);
        }

        $emergencyService = app(EmergencyCounterService::class);
        $variance = $emergencyService->getVariance($closure);

        return view('counters.emergency-closure', compact('counter', 'closure', 'variance'));
    }

    public function acknowledgeEmergency(Request $request, Counter $counter, EmergencyClosure $closure)
    {
        $this->requireManagerOrAdmin();

        if ($closure->counter_id !== $counter->id) {
            abort(404);
        }

        $user = Auth::user();
        $emergencyService = app(EmergencyCounterService::class);

        $closure = $emergencyService->acknowledge($closure, $user);

        return redirect()->route('counters.index')
            ->with('success', 'Emergency closure acknowledged');
    }

    public function showAcknowledgeHandover(Counter $counter)
    {
        $user = Auth::user();
        $today = now()->toDateString();

        $handover = CounterHandover::with(['counterSession', 'fromUser', 'supervisor'])
            ->whereHas('counterSession', function ($query) use ($counter, $today) {
                $query->where('counter_id', $counter->id)
                    ->where('session_date', $today);
            })
            ->where('to_user_id', $user->id)
            ->whereNull('acknowledged_at')
            ->first();

        if (! $handover) {
            return redirect()->route('counters.index')
                ->with('error', 'No pending handover to acknowledge');
        }

        return view('counters.acknowledge-handover', compact('counter', 'handover'));
    }

    public function acknowledgeHandover(Request $request, Counter $counter)
    {
        $user = Auth::user();
        $today = now()->toDateString();

        $handover = CounterHandover::with(['counterSession', 'fromUser', 'supervisor'])
            ->whereHas('counterSession', function ($query) use ($counter, $today) {
                $query->where('counter_id', $counter->id)
                    ->where('session_date', $today);
            })
            ->where('to_user_id', $user->id)
            ->whereNull('acknowledged_at')
            ->first();

        if (! $handover) {
            return back()->with('error', 'No pending handover to acknowledge');
        }

        $request->validate([
            'verified' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $handoverService = app(CounterHandoverService::class);

        try {
            $handoverService->acknowledgeHandover(
                $handover,
                $user,
                $request->boolean('verified'),
                $request->input('notes')
            );

            return redirect()->route('counters.index')
                ->with('success', 'Handover acknowledged successfully');
        } catch (\Exception $e) {
            return back()->with('error', "Failed to acknowledge handover: {$e->getMessage()}");
        }
    }
}
