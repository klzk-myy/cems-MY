<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\MonthEndPreCheckFailedException;
use App\Services\MonthEndCloseService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonthEndCloseController extends Controller
{
    public function __construct(
        protected MonthEndCloseService $monthEndCloseService
    ) {}

    public function index(Request $request): View
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : now()->subMonth();

        $status = $this->monthEndCloseService->getMonthEndStatus($date);

        return view('accounting.month-end', [
            'status' => $status,
            'selectedDate' => $date->toDateString(),
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : now()->subMonth()->endOfMonth();

        $user = $request->user();

        try {
            $results = $this->monthEndCloseService->runMonthEndClosing($date, $user);

            return redirect()->back()->with('success', 'Month-end close completed successfully.');
        } catch (MonthEndPreCheckFailedException $e) {
            return redirect()->back()->with('error', 'Pre-flight checks failed: '.implode(', ', $e->getFailures()));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Month-end close failed: '.$e->getMessage());
        }
    }
}
