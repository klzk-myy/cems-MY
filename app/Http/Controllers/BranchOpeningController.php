<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Currency;
use App\Services\AccountingService;
use App\Services\BranchPoolService;
use App\Services\MathService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchOpeningController extends Controller
{
    public function index()
    {
        return view('branch-openings.index');
    }

    public function step1()
    {
        $branchTypes = [
            'head_office' => 'Head Office',
            'branch' => 'Branch',
            'sub_branch' => 'Sub-Branch',
        ];

        $parentBranches = Branch::where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('branch-openings.step1', compact('branchTypes', 'parentBranches'));
    }

    public function processStep1(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:branches,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:head_office,branch,sub_branch',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'parent_id' => 'nullable|exists:branches,id',
        ]);

        if ($request->boolean('is_main')) {
            Branch::where('is_main', true)->update(['is_main' => false]);
        }

        $branch = Branch::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? 'Malaysia',
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => true,
            'is_main' => $request->boolean('is_main') ?? false,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return redirect()->route('branches.open.step2', ['branch' => $branch->id]);
    }

    public function step2(Branch $branch)
    {
        $currencies = Currency::where('is_active', true)->get();
        $existingPools = $branch->branchPools()->get()->pluck('available_balance', 'currency_code')->toArray();

        return view('branch-openings.step2', compact('branch', 'currencies', 'existingPools'));
    }

    public function processStep2(Request $request, Branch $branch)
    {
        $currencies = Currency::where('is_active', true)->get();
        $poolService = app(BranchPoolService::class);

        foreach ($currencies as $currency) {
            $amount = $request->input("pool_{$currency->code}");
            if ($amount && is_numeric($amount) && $amount > 0) {
                $poolService->replenish($branch, $currency->code, $amount, auth()->id());
            }
        }

        return redirect()->route('branches.open.step3', ['branch' => $branch->id]);
    }

    public function step3(Branch $branch)
    {
        $totalPoolAmount = $this->calculateTotalPoolAmount($branch);

        return view('branch-openings.step3', compact('branch', 'totalPoolAmount'));
    }

    public function processStep3(Request $request, Branch $branch, AccountingService $accountingService)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:100',
        ]);

        $amount = $validated['amount'];
        $reference = $validated['reference'] ?? "Opening balance for {$branch->code}";

        return DB::transaction(function () use ($branch, $amount, $reference, $accountingService) {
            // Journal entry is now posted directly without approval workflow
            $entry = $accountingService->createJournalEntry([
                [
                    'account_code' => '1010',
                    'debit' => $amount,
                    'credit' => '0',
                    'description' => "Initial capital - {$branch->name}",
                ],
                [
                    'account_code' => '3000',
                    'debit' => '0',
                    'credit' => $amount,
                    'description' => "Owner's capital contribution - {$branch->name}",
                ],
            ], 'Opening Balance', null, $reference, now()->toDateString(), auth()->id());

            return redirect()->route('branches.open.complete', ['branch' => $branch->id, 'entry' => $entry->id]);
        });
    }

    public function complete(Branch $branch)
    {
        $stats = [
            'pool_count' => $branch->branchPools()->count(),
            'total_currencies' => $branch->branchPools()->where('available_balance', '>', 0)->count(),
        ];

        return view('branch-openings.complete', compact('branch', 'stats'));
    }

    private function calculateTotalPoolAmount(Branch $branch): string
    {
        $total = '0';
        $mathService = app(MathService::class);

        foreach ($branch->branchPools as $pool) {
            $total = $mathService->add($total, $pool->available_balance ?? '0');
        }

        return $total;
    }
}
