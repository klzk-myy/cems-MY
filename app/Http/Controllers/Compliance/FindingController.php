<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FindingController extends Controller
{
    protected string $apiBase = '/api/v1/compliance/findings';

    public function index(Request $request)
    {
        $params = array_filter([
            'status' => $request->get('status'),
            'severity' => $request->get('severity'),
            'type' => $request->get('type'),
            'from_date' => $request->get('from_date'),
            'to_date' => $request->get('to_date'),
        ]);

        $url = config('app.url').$this->apiBase;
        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        try {
            $response = Http::withToken(session('api_token'))
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json() ?? [];
            } else {
                $data = [];
                Log::warning('FindingController: Failed to fetch findings', [
                    'status' => $response->status(),
                    'endpoint' => $url,
                ]);
            }
        } catch (\Exception $e) {
            $data = [];
            Log::error('FindingController: Exception fetching findings', [
                'message' => $e->getMessage(),
                'endpoint' => $url,
            ]);
        }

        $findings = $data['data'] ?? [];

        try {
            $statsResponse = Http::withToken(session('api_token'))
                ->timeout(10)
                ->get(config('app.url').$this->apiBase.'/stats');

            if ($statsResponse->successful()) {
                $stats = ($statsResponse->json() ?? [])['data'] ?? [];
            } else {
                $stats = [];
                Log::warning('FindingController: Failed to fetch stats', [
                    'status' => $statsResponse->status(),
                    'endpoint' => $this->apiBase.'/stats',
                ]);
            }
        } catch (\Exception $e) {
            $stats = [];
            Log::error('FindingController: Exception fetching stats', [
                'message' => $e->getMessage(),
                'endpoint' => $this->apiBase.'/stats',
            ]);
        }

        $pagination = [
            'current_page' => $data['current_page'] ?? 1,
            'last_page' => $data['last_page'] ?? 1,
            'per_page' => $data['per_page'] ?? 25,
            'total' => $data['total'] ?? 0,
        ];

        return view('compliance.findings.index', compact('findings', 'stats', 'pagination'));
    }

    public function show(int $id)
    {
        try {
            $response = Http::withToken(session('api_token'))
                ->timeout(10)
                ->get(config('app.url').$this->apiBase.'/'.$id);

            if (! $response->successful()) {
                return redirect()->route('compliance.findings.index')
                    ->with('error', 'Finding not found');
            }

            $finding = $response->json()['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('FindingController: Exception fetching finding', [
                'message' => $e->getMessage(),
                'finding_id' => $id,
                'endpoint' => $this->apiBase.'/'.$id,
            ]);

            return redirect()->route('compliance.findings.index')
                ->with('error', 'Finding not found');
        }

        return view('compliance.findings.show', compact('finding'));
    }

    public function dismiss(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $response = Http::withToken(session('api_token'))
                ->timeout(10)
                ->post(config('app.url').$this->apiBase.'/'.$id.'/dismiss', [
                    'reason' => $validated['reason'],
                ]);

            if ($response->successful()) {
                return redirect()->back()->with('success', 'Finding dismissed');
            }

            Log::warning('FindingController: Failed to dismiss finding', [
                'status' => $response->status(),
                'finding_id' => $id,
                'endpoint' => $this->apiBase.'/'.$id.'/dismiss',
            ]);

            return redirect()->back()->with('error', $response->json()['message'] ?? 'Failed to dismiss finding');
        } catch (\Exception $e) {
            Log::error('FindingController: Exception dismissing finding', [
                'message' => $e->getMessage(),
                'finding_id' => $id,
                'endpoint' => $this->apiBase.'/'.$id.'/dismiss',
            ]);

            return redirect()->back()->with('error', 'Failed to dismiss finding');
        }
    }
}
