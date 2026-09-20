<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScreeningController extends Controller
{
    use HandlesControllerErrors;

    public function __construct(
        protected CustomerScreeningService $screeningService,
    ) {}

    public function show(int $customerId): View
    {
        $customer = Customer::findOrFail($customerId);

        $status = $this->screeningService->getStatus($customer);

        $history = $this->screeningService->getHistory($customer)
            ->map(fn ($r) => $r->toArray());

        return view('compliance.screening.show', [
            'customer' => $customer,
            'status' => $status,
            'history' => $history,
        ]);
    }

    public function screen(Request $request, int $customerId): RedirectResponse
    {
        $customer = Customer::findOrFail($customerId);

        try {
            $response = $this->screeningService->screenCustomer($customer);

            if ($response->action === 'clear') {
                return redirect()->back()->with('success', 'Customer screened successfully');
            }

            return redirect()->back()->with('warning', 'Customer screening resulted in: '.$response->action);
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'ScreeningController: Exception screening customer', 'Failed to screen customer', ['customer_id' => $customerId]);
        }
    }

    public function history(int $customerId): View
    {
        $customer = Customer::findOrFail($customerId);

        $history = $this->screeningService->getHistory($customer)
            ->map(fn ($r) => $r->toArray())
            ->paginate(25);

        return view('compliance.screening.history', compact('customer', 'history'));
    }

    public function status(int $customerId): View
    {
        $customer = Customer::findOrFail($customerId);

        $status = $this->screeningService->getStatus($customer);

        return view('compliance.screening.status', compact('customer', 'status'));
    }
}
