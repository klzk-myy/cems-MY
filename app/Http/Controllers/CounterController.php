<?php

namespace App\Http\Controllers;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CounterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CounterController extends Controller
{
    private CounterService $counterService;

    private AuditService $auditService;

    public function __construct(CounterService $counterService, AuditService $auditService)
    {
        $this->counterService = $counterService;
        $this->auditService = $auditService;
    }

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
        $currencies = \App\Models\Currency::where('is_active', true)->get();

        return view('counters.index', compact('counters', 'stats', 'availableCounters', 'currencies'));
    }

    /**
     * Show the form for opening a counter
     */
    public function showOpen(Counter $counter)
    {
        $availableCounters = $this->counterService->getAvailableCounters();
        $currencies = \App\Models\Currency::where('is_active', true)->get();

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
            return back()->with('error', $e->getMessage());
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

        $currencies = \App\Models\Currency::where('is_active', true)->get();

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
            return back()->with('error', $e->getMessage());
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

        $currencies = \App\Models\Currency::where('is_active', true)->get();

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
            return back()->with('error', $e->getMessage());
        }
    }
}
