<?php

require '/www/wwwroot/local.host/vendor/autoload.php';
$app = require_once '/www/wwwroot/local.host/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\CounterSession;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

echo "Creating test data...\n";
Log::info('Test setup started');

DB::beginTransaction();

try {
    $manager = User::create([
        'username' => 'manager_test',
        'email' => 'manager@test.com',
        'is_active' => true,
    ]);
    $manager->role = 'manager';
    $manager->password_hash = Hash::make('password');
    $manager->save();
    Log::info('Manager created', ['user_id' => $manager->id, 'role' => 'manager']);
    echo "  Created manager\n";

    $branches = Branch::all();
    $tellers = [];
    foreach ($branches as $branch) {
        $teller = User::create([
            'username' => 'teller_'.strtolower($branch->code),
            'email' => 'teller.'.strtolower($branch->code).'@test.com',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $teller->role = 'teller';
        $teller->password_hash = Hash::make('password');
        $teller->save();
        $tellers[$branch->id] = $teller;
        Log::info('Teller created', ['user_id' => $teller->id, 'branch' => $branch->code]);
        echo '  Created teller for '.$branch->code."\n";
    }

    $customers = [];
    for ($i = 1; $i <= 10; $i++) {
        $idNumber = '80010101501'.str_pad($i, 3, '0', STR_PAD_LEFT);
        $customer = Customer::create([
            'full_name' => 'Customer '.$i,
            'email' => 'customer'.$i.'@test.com',
            'phone' => '+601234567899',
            'id_type' => 'MyKad',
            'id_number_encrypted' => $idNumber,
            'id_number_hash' => hash_hmac('sha256', $idNumber, config('app.key')),
            'nationality' => 'Malaysian',
            'date_of_birth' => '1990-01-01',
            'address' => 'Address '.$i,
            'occupation' => 'Business',
        ]);
        $customers[] = $customer;
        Log::info('Customer created', ['customer_id' => $customer->id]);
    }
    echo '  Created '.count($customers)." customers\n";

    foreach ($branches as $branch) {
        $teller = $tellers[$branch->id];

        TillBalance::create([
            'till_id' => $branch->code.'_TILL',
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'user_id' => $teller->id, 'opened_by' => $teller->id,
            'date' => today(),
            'opening_balance' => '100000.00',
            'current_balance' => '100000.00',
        ]);

        foreach (['USD', 'EUR', 'GBP'] as $currencyCode) {
            $position = CurrencyPosition::firstOrCreate(
                ['currency_code' => $currencyCode, 'till_id' => $branch->code.'_TILL'],
                ['balance' => '0', 'avg_cost_rate' => '0', 'last_valuation_rate' => '0']
            );

            $pool = BranchPool::where('branch_id', $branch->id)
                ->where('currency_code', $currencyCode)
                ->first();

            if ($pool) {
                $position->balance = $pool->available_balance;
                $position->avg_cost_rate = '4.50';
                $position->last_valuation_rate = '4.50';
                $position->save();
                Log::info('Position initialized', [
                    'position_id' => $position->id,
                    'branch' => $branch->code, 'currency' => $currencyCode,
                ]);
            }

            CounterSession::create([
                'counter_id' => $branch->code.'_COUNTER',
                'user_id' => $teller->id, 'opened_by' => $teller->id,
                'branch_id' => $branch->id,
                'status' => 'open',
                'opening_float' => '1000.00',
            ]);
        }
    }

    DB::commit();
    Log::info('Test setup completed', [
        'branches' => $branches->count(),
        'tellers' => count($tellers),
        'customers' => count($customers),
    ]);

    echo "\nSetup complete!\n";
    echo 'Branches: '.$branches->count()."\n";
    echo 'Tellers: '.count($tellers)."\n";
    echo 'Customers: '.count($customers)."\n";

} catch (Exception $e) {
    DB::rollBack();
    Log::error('Test setup failed', ['error' => $e->getMessage()]);
    echo 'Failed: '.$e->getMessage()."\n";
}
