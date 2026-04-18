<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $filters = $request->only(['status', 'priority', 'category', 'assigned_to']);

        $tasks = $this->taskService->getAllTasks($filters, $user);

        $users = User::where('is_active', true)->get();

        return view('tasks.index', compact('tasks', 'users'));
    }

    public function show(Task $task)
    {
        $task->load(['assignedTo', 'createdBy', 'completedBy', 'relatedCustomer', 'relatedTransaction']);

        return view('tasks.show', compact('task'));
    }

    public function create(Request $request)
    {
        $users = User::where('is_active', true)->get();
        $categories = ['Compliance', 'Customer', 'Operations', 'Admin', 'Approval'];
        $priorities = ['Urgent', 'High', 'Medium', 'Low'];

        $relatedCustomerId = $request->input('customer_id');
        $relatedTransactionId = $request->input('transaction_id');

        return view('tasks.create', compact('users', 'categories', 'priorities', 'relatedCustomerId', 'relatedTransactionId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'category' => 'required|in:Compliance,Customer,Operations,Admin,Approval',
            'priority' => 'required|in:Urgent,High,Medium,Low',
            'assigned_to' => 'nullable|exists:users,id',
            'assigned_role' => 'nullable|string',
            'due_at' => 'nullable|date',
            'related_customer_id' => 'nullable|exists:customers,id',
            'related_transaction_id' => 'nullable|exists:transactions,id',
            'notes' => 'nullable|string',
        ]);

        $task = $this->taskService->createTask($validated, Auth::id());

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task created successfully');
    }

    public function acknowledge(Task $task)
    {
        if ($task->acknowledged_at) {
            return back()->with('error', 'Task already acknowledged');
        }

        $this->taskService->acknowledgeTask($task, Auth::id());

        return back()->with('success', 'Task acknowledged');
    }

    public function complete(Request $request, Task $task)
    {
        $validated = $request->validate([
            'completion_notes' => 'nullable|string',
        ]);

        $this->taskService->completeTask($task, Auth::id(), $validated['completion_notes'] ?? null);

        return redirect()->route('tasks.index')
            ->with('success', 'Task completed successfully');
    }

    public function cancel(Request $request, Task $task)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $this->taskService->cancelTask($task, Auth::id(), $validated['notes'] ?? null);

        return redirect()->route('tasks.index')
            ->with('success', 'Task cancelled');
    }

    public function escalate(Task $task)
    {
        $this->taskService->escalateTask($task, Auth::id());

        return back()->with('success', 'Task escalated to '.$task->fresh()->priority);
    }

    public function myTasks()
    {
        $tasks = $this->taskService->getUserTasks(Auth::id());

        return view('tasks.my-tasks', compact('tasks'));
    }

    public function overdue()
    {
        $tasks = $this->taskService->getOverdueTasks();

        return view('tasks.overdue', compact('tasks'));
    }

    public function stats()
    {
        $stats = $this->taskService->getTaskStats();

        return response()->json($stats);
    }
}
