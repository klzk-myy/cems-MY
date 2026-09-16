<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnifiedAlertIndexRequest;
use App\Models\Alert;
use App\Services\Compliance\UnifiedAlertQueryService;
use App\ValueObjects\UnifiedAlertFilters;
use Illuminate\View\View;

class UnifiedAlertController extends Controller
{
    public function __construct(
        protected UnifiedAlertQueryService $unifiedAlerts,
    ) {}

    public function index(UnifiedAlertIndexRequest $request): View
    {
        $this->authorize('viewAny', Alert::class);

        $result = $this->unifiedAlerts->page(UnifiedAlertFilters::fromRequest($request));

        return view('compliance.unified.index', [
            'items' => $result['items'],
            'stats' => $result['stats'],
            'pagination' => $result['pagination'],
            'request' => $request,
        ]);
    }
}
