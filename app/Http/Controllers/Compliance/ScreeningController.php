<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScreeningController extends Controller
{
    protected string $apiBase = '/api/v1/screening';

    public function show(int $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        try {
            $statusResponse = Http::withToken(session('api_token'))
                ->timeout(10)
                ->get(config('app.url').$this->apiBase.'/customer/'.$customerId.'/status');

            if ($statusResponse->successful()) {
                $status = $statusResponse->json()['data'] ?? [];
            } else {
                $status = [];
                Log::warning('ScreeningController: Failed to fetch status', [
                    'status' => $statusResponse->status(),
                    'customer_id' => $customerId,
                    'endpoint' => $this->apiBase.'/customer/'.$customerId.'/status',
                ]);
            }
        } catch (\Exception $e) {
            $status = [];
            Log::error('ScreeningController: Exception fetching status', [
                'message' => $e->getMessage(),
                'customer_id' => $customerId,
                'endpoint' => $this->apiBase.'/customer/'.$customerId.'/status',
            ]);
        }

        try {
            $historyResponse = Http::withToken(session('api_token'))
                ->timeout(10)
                ->get(config('app.url').$this->apiBase.'/customer/'.$customerId.'/history');

            if ($historyResponse->successful()) {
                $history = $historyResponse->json()['data'] ?? [];
            } else {
                $history = [];
                Log::warning('ScreeningController: Failed to fetch history', [
                    'status' => $historyResponse->status(),
                    'customer_id' => $customerId,
                    'endpoint' => $this->apiBase.'/customer/'.$customerId.'/history',
                ]);
            }
        } catch (\Exception $e) {
            $history = [];
            Log::error('ScreeningController: Exception fetching history', [
                'message' => $e->getMessage(),
                'customer_id' => $customerId,
                'endpoint' => $this->apiBase.'/customer/'.$customerId.'/history',
            ]);
        }

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
            $response = Http::withToken(session('api_token'))
                ->timeout(10)
                ->post(config('app.url').$this->apiBase.'/customer/'.$customerId);

            if ($response->successful()) {
                return redirect()->back()->with('success', 'Customer screened successfully');
            }

            Log::warning('ScreeningController: Failed to screen customer', [
                'status' => $response->status(),
                'customer_id' => $customerId,
                'endpoint' => $this->apiBase.'/customer/'.$customerId,
            ]);

            return redirect()->back()->with('error', $response->json()['message'] ?? 'Failed to screen customer');
        } catch (\Exception $e) {
            Log::error('ScreeningController: Exception screening customer', [
                'message' => $e->getMessage(),
                'customer_id' => $customerId,
                'endpoint' => $this->apiBase.'/customer/'.$customerId,
            ]);

            return redirect()->back()->with('error', 'Failed to screen customer');
        }
    }
}
