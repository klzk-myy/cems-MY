<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TestQueryLogController extends Controller
{
    public function index(): JsonResponse
    {
        $count = Customer::count();

        return response()->json([
            'queries' => DB::getQueryLog(),
            'customer_count' => $count,
        ]);
    }
}
