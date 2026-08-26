<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SystemLog::class);

        $logs = SystemLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.$request->string('action').'%'))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'unsealedCount' => app(AuditService::class)->getUnsealedCount(),
        ]);
    }

    public function show(SystemLog $log): View
    {
        $this->authorize('view', $log);

        return view('admin.audit-logs.show', [
            'log' => $log,
            'prevId' => SystemLog::query()->where('id', '<', $log->id)->max('id'),
            'nextId' => SystemLog::query()->where('id', '>', $log->id)->min('id'),
        ]);
    }
}
