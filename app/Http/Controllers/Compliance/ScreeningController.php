<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerScreeningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScreeningController extends Controller
{
    public function __construct(
        protected CustomerScreeningService $screeningService,
    ) {}

    public function show(int $customerId)
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

    public function screen(Request $request, int $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        try {
            $response = $this->screeningService->screenCustomer($customer);

            if ($response->action === 'clear') {
                return redirect()->back()->with('success', 'Customer screened successfully');
            }

            return redirect()->back()->with('warning', 'Customer screening resulted in: '.$response->action);
        } catch (\Exception $e) {
            Log::error('ScreeningController: Exception screening customer', [
                'message' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);

            return redirect()->back()->with('error', 'Failed to screen customer');
        }
    }
}
