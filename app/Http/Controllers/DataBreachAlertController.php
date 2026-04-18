<?php

namespace App\Http\Controllers;

use App\Models\DataBreachAlert;
use Illuminate\Http\Request;

class DataBreachAlertController extends Controller
{
    public function index(Request $request)
    {
        $query = DataBreachAlert::query();

        if ($request->has('is_resolved')) {
            $query->where('is_resolved', $request->boolean('is_resolved'));
        }

        $alerts = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('data-breach-alerts.index', compact('alerts'));
    }

    public function show(DataBreachAlert $dataBreachAlert)
    {
        return view('data-breach-alerts.show', compact('dataBreachAlert'));
    }

    public function resolve(Request $request, DataBreachAlert $dataBreachAlert)
    {
        $dataBreachAlert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Alert resolved');
    }
}
