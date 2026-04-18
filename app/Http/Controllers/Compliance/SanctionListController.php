<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SanctionListController extends Controller
{
    protected string $apiBase = '/api/v1/sanctions';

    public function index()
    {
        $response = Http::withToken(session('api_token'))->get(config('app.url').$this->apiBase.'/lists');

        $lists = $response->successful() ? ($response->json() ?? []) : [];

        return view('compliance.sanctions.index', compact('lists'));
    }

    public function show(int $id)
    {
        $response = Http::withToken(session('api_token'))->get(config('app.url').$this->apiBase.'/lists/'.$id);

        if (! $response->successful()) {
            return redirect()->route('compliance.sanctions.index')
                ->with('error', 'Sanction list not found');
        }

        $list = $response->json() ?? [];

        return view('compliance.sanctions.show', compact('list'));
    }

    public function entriesIndex(Request $request)
    {
        $params = array_filter([
            'search' => $request->get('search'),
            'list_id' => $request->get('list_id'),
            'status' => $request->get('status'),
            'type' => $request->get('type'),
        ]);

        $url = config('app.url').$this->apiBase.'/entries';
        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        $response = Http::withToken(session('api_token'))->get($url);

        $data = $response->successful() ? ($response->json() ?? []) : [];

        $entries = $data['data'] ?? [];
        $pagination = [
            'current_page' => $data['current_page'] ?? 1,
            'last_page' => $data['last_page'] ?? 1,
            'per_page' => $data['per_page'] ?? 25,
            'total' => $data['total'] ?? 0,
        ];

        $listsResponse = Http::withToken(session('api_token'))->get(config('app.url').$this->apiBase.'/lists');
        $lists = $listsResponse->successful() ? $listsResponse->json() : [];

        return view('compliance.sanctions.entries.index', compact('entries', 'pagination', 'lists'));
    }

    public function showEntry(int $id)
    {
        $response = Http::withToken(session('api_token'))->get(config('app.url').$this->apiBase.'/entries/'.$id);

        if (! $response->successful()) {
            return redirect()->route('compliance.sanctions.entries.index')
                ->with('error', 'Sanction entry not found');
        }

        $entry = $response->json() ?? [];

        return view('compliance.sanctions.entries.show', compact('entry'));
    }

    public function createEntry()
    {
        $listsResponse = Http::withToken(session('api_token'))->get(config('app.url').$this->apiBase.'/lists');
        $lists = $listsResponse->successful() ? $listsResponse->json() : [];

        return view('compliance.sanctions.entries.create', compact('lists'));
    }

    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
            'list_id' => 'required|integer',
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'required|in:individual,entity',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|string',
        ]);

        $response = Http::withToken(session('api_token'))
            ->post(config('app.url').$this->apiBase.'/entries', $validated);

        if ($response->successful()) {
            return redirect()->route('compliance.sanctions.entries.index')
                ->with('success', 'Sanction entry created successfully');
        }

        return redirect()->back()
            ->with('error', $response->json()['message'] ?? 'Failed to create entry')
            ->withInput();
    }

    public function editEntry(int $id)
    {
        $entryResponse = Http::withToken(session('api_token'))->get(config('app.url').$this->apiBase.'/entries/'.$id);

        if (! $entryResponse->successful()) {
            return redirect()->route('compliance.sanctions.entries.index')
                ->with('error', 'Sanction entry not found');
        }

        $entry = $entryResponse->json();

        $listsResponse = Http::withToken(session('api_token'))->get(config('app.url').$this->apiBase.'/lists');
        $lists = $listsResponse->successful() ? $listsResponse->json() : [];

        return view('compliance.sanctions.entries.edit', compact('entry', 'lists'));
    }

    public function updateEntry(Request $request, int $id)
    {
        $validated = $request->validate([
            'list_id' => 'required|integer',
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'required|in:individual,entity',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|string',
        ]);

        $response = Http::withToken(session('api_token'))
            ->put(config('app.url').$this->apiBase.'/entries/'.$id, $validated);

        if ($response->successful()) {
            return redirect()->route('compliance.sanctions.entries.show', $id)
                ->with('success', 'Sanction entry updated successfully');
        }

        return redirect()->back()
            ->with('error', $response->json()['message'] ?? 'Failed to update entry')
            ->withInput();
    }

    public function importLogs()
    {
        $response = Http::withToken(session('api_token'))
            ->get(config('app.url').$this->apiBase.'/import/logs');

        $logs = $response->successful() ? ($response->json() ?? []) : [];

        return view('compliance.sanctions.import-logs.index', compact('logs'));
    }

    public function triggerImport(int $listId)
    {
        try {
            $response = Http::withToken(session('api_token'))
                ->post(config('app.url').$this->apiBase.'/import/trigger/'.$listId);

            if ($response->successful()) {
                return redirect()->back()->with('success', 'Import triggered successfully');
            }

            return redirect()->back()->with('error', $response->json()['message'] ?? 'Failed to trigger import');
        } catch (RequestException $e) {
            return redirect()->back()->with('error', 'Failed to trigger import: '.$e->getMessage());
        }
    }
}
