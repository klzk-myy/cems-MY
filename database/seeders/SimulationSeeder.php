<?php

namespace Database\Seeders;

use App\Enums\CddLevel;
use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * SimulationSeeder — populates the test database with a small, stable
 * identity graph for the black-box simulation harness.
 *
 * Seeded state: 3 branches, 3 counters, 4 users (teller / manager /
 * compliance / admin), 1 open counter session, till balances + teller
 * allocations for USD/EUR/GBP/MYR, 1 customer, and 10 transactions spanning
 * every active status so waves can exercise the full state machine.
 */
class SimulationSeeder extends Seeder
{
    public function run(): void
    {
        $this->createCurrencies();
        [$hq, $br1, $br2] = $this->createBranches();
        [$counterHq, $counterBr1, $counterBr2] = $this->createCounters($hq, $br1, $br2);
        [$teller, $manager, $compliance, $admin] = $this->createUsers($hq, $counterHq);
        // No pre-opened counter session or till balances: Wave A step A2 opens the
        // counter, which creates the till balances. Later steps depend on
        // those being created by the open flow.
        $this->createBranchPools($hq);
        $this->createExchangeRates($hq);
        $this->createAllocations($hq, $counterHq, $teller);
        $customer = $this->createCustomer($hq, $teller);
        $this->createTransactions($counterHq, $hq, $customer, $teller);
    }

    private function createCurrencies(): void
    {
        $currencies = [
            ['USD', 'US Dollar', '$', 2, true],
            ['EUR', 'Euro', '€', 2, true],
            ['GBP', 'British Pound', '£', 2, true],
            ['SGD', 'Singapore Dollar', 'S$', 2, true],
            ['JPY', 'Japanese Yen', '¥', 0, true],
            ['MYR', 'Malaysian Ringgit', 'RM', 2, true],
            // The rate API returns rates for these currencies too; they must
            // exist so the exchange_rates.currency_code FK is satisfied when
            // the revaluation fetches and stores them.
            ['AUD', 'Australian Dollar', 'A$', 2, true],
            ['CAD', 'Canadian Dollar', 'C$', 2, true],
            ['CHF', 'Swiss Franc', 'Fr', 2, true],
        ];
        foreach ($currencies as [$code, $name, $symbol, $places, $active]) {
            Currency::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'symbol' => $symbol, 'decimal_places' => $places, 'is_active' => $active]
            );
        }
    }

    /**
     * @return list<Branch>
     */
    private function createBranches(): array
    {
        $hq = Branch::updateOrCreate(
            ['code' => 'HQ'],
            ['name' => 'Headquarters', 'type' => 'main', 'country' => 'MY', 'is_active' => true]
        );
        $br1 = Branch::updateOrCreate(
            ['code' => 'BR001'],
            ['name' => 'Branch One', 'type' => 'branch', 'country' => 'MY', 'is_active' => true]
        );
        $br2 = Branch::updateOrCreate(
            ['code' => 'BR002'],
            ['name' => 'Branch Two', 'type' => 'branch', 'country' => 'MY', 'is_active' => true]
        );

        return [$hq, $br1, $br2];
    }

    /**
     * @return list<Counter>
     */
    private function createCounters(Branch $hq, Branch $br1, Branch $br2): array
    {
        $c1 = Counter::updateOrCreate(
            ['code' => 'C001'],
            ['name' => 'HQ Counter', 'branch_id' => $hq->id, 'status' => 'active']
        );
        $c2 = Counter::updateOrCreate(
            ['code' => 'C002'],
            ['name' => 'Branch One Counter', 'branch_id' => $br1->id, 'status' => 'active']
        );
        $c3 = Counter::updateOrCreate(
            ['code' => 'C003'],
            ['name' => 'Branch Two Counter', 'branch_id' => $br2->id, 'status' => 'active']
        );

        return [$c1, $c2, $c3];
    }

    /**
     * @return list<User>
     */
    private function createUsers(Branch $hq, Counter $counter): array
    {
        $make = function (string $username, string $email, UserRole $role, Branch $branch) {
            // password_hash, role and mfa_enabled are not fillable — an
            // updateOrCreate attributes array would silently drop them (and
            // 'password' must be plain: the mutator hashes it, so a pre-hashed
            // value ends up double-hashed and unusable for login).
            $user = User::firstOrNew(['username' => $username]);
            $user->fill([
                'email' => $email,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);
            $user->password = 'Test@1234';
            $user->role = $role;
            $user->mfa_enabled = false;
            $user->save();

            return $user;
        };

        $teller = $make('sim_teller', 'sim_teller@cems.my', UserRole::Teller, $hq);
        $manager = $make('sim_manager', 'sim_manager@cems.my', UserRole::Manager, $hq);
        // Second manager so the cancellation flow can satisfy segregation of
        // duties on the web surface (requester and approver must differ, and
        // the web cancellation routes are role:manager only).
        $make('sim_manager2', 'sim_manager2@cems.my', UserRole::Manager, $hq);
        $compliance = $make('sim_compliance', 'sim_compliance@cems.my', UserRole::ComplianceOfficer, $hq);
        $admin = $make('sim_admin', 'sim_admin@cems.my', UserRole::Admin, $hq);

        return [$teller, $manager, $compliance, $admin];
    }

    /**
     * Seed official exchange rates so the rate-deviation guard
     * (thresholds.rates.max_deviation_percent) is active during simulations.
     * Wave A books USD at exactly 4.50 (0% deviation, passes); Wave B books
     * the same currency at 9.00 (100% deviation, must be rejected).
     */
    private function createExchangeRates(Branch $branch): void
    {
        $rates = [
            ['USD', '4.5000', '4.6000'],
            ['EUR', '4.7900', '4.8500'],
            ['GBP', '5.1000', '5.2000'],
        ];
        foreach ($rates as [$code, $buy, $sell]) {
            ExchangeRate::updateOrCreate(
                ['branch_id' => $branch->id, 'currency_code' => $code],
                ['rate_buy' => $buy, 'rate_sell' => $sell, 'source' => 'simulation', 'fetched_at' => now()]
            );
        }
    }

    private function createBranchPools(Branch $branch): void
    {
        foreach (['USD', 'EUR', 'GBP', 'MYR'] as $currency) {
            BranchPool::updateOrCreate(
                ['branch_id' => $branch->id, 'currency_code' => $currency],
                ['available_balance' => 500000, 'allocated_balance' => 0]
            );
        }
    }

    private function createAllocations(Branch $branch, Counter $counter, User $teller): void
    {
        foreach (['USD', 'EUR', 'GBP'] as $currency) {
            TellerAllocation::updateOrCreate(
                ['user_id' => $teller->id, 'currency_code' => $currency, 'session_date' => now()->toDateString()],
                [
                    'branch_id' => $branch->id,
                    'counter_id' => $counter->id,
                    'allocated_amount' => 50000,
                    'current_balance' => 50000,
                    'requested_amount' => 50000,
                    'daily_limit_myr' => 100000,
                    'daily_used_myr' => 0,
                    'status' => TellerAllocationStatus::ACTIVE->value,
                    'opened_at' => now(),
                ]
            );
        }
    }

    private function createCustomer(Branch $branch, User $createdBy): Customer
    {
        return Customer::updateOrCreate(
            ['email' => 'sim_customer@cems.my'],
            [
                'full_name' => 'Sim Customer',
                'id_type' => 'MyKad',
                'id_number_encrypted' => encrypt('900101-01-1234'),
                'nationality' => 'MY',
                'date_of_birth' => '1990-01-01',
                'customer_type' => 'individual',
                'is_active' => true,
                'pep_status' => false,
                'is_pep_associate' => false,
            ]
        );
    }

    private function createTransactions(Counter $counter, Branch $branch, Customer $customer, User $teller): void
    {
        $specs = [
            ['Buy', 'USD', '100', '4.50', TransactionStatus::PendingApproval->value],
            ['Buy', 'USD', '200', '4.52', TransactionStatus::Approved->value],
            ['Sell', 'EUR', '150', '4.80', TransactionStatus::Completed->value],
            ['Buy', 'GBP', '300', '4.95', TransactionStatus::Cancelled->value],
            ['Buy', 'USD', '50', '4.50', TransactionStatus::Draft->value],
            ['Buy', 'USD', '75', '4.51', TransactionStatus::Reversed->value],
            ['Sell', 'SGD', '400', '3.40', TransactionStatus::Failed->value],
            ['Buy', 'USD', '120', '4.53', TransactionStatus::Rejected->value],
            ['Buy', 'USD', '60', '4.50', TransactionStatus::PendingCancellation->value],
            ['Buy', 'EUR', '250', '4.81', TransactionStatus::Completed->value],
        ];
        foreach ($specs as [$type, $currency, $foreign, $rate, $status]) {
            $myr = number_format((float) $foreign * (float) $rate, 2, '.', '');
            $tx = new Transaction;
            $tx->fill([
                'customer_id' => $customer->id,
                'user_id' => $teller->id,
                'branch_id' => $branch->id,
                'counter_id' => $counter->id,
                'till_id' => (string) $counter->code,
                'type' => $type === 'Buy' ? TransactionType::Buy : TransactionType::Sell,
                'currency_code' => $currency,
                'amount_foreign' => $foreign,
                'rate' => $rate,
                'amount_local' => $myr,
                'purpose' => 'Simulated transaction',
                'source_of_funds' => 'Salary',
            ]);
            $tx->idempotency_key = uniqid('sim_');
            $tx->cdd_level = CddLevel::Simplified;
            $tx->status = TransactionStatus::from($status);
            // Backdate outside the 1-hour structuring window so the seeded
            // history does not trip structuring detection when the harness
            // books and approves its own transactions. Same calendar day, so
            // EOD and reporting scopes still include them.
            $tx->created_at = now()->subHours(3);
            $tx->updated_at = now()->subHours(3);
            $tx->save();
        }
    }
}
