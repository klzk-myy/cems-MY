<?php

namespace Tests\Feature\Report;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function monthly_trends_excludes_cancelled_transactions(): void
    {
        $admin = User::factory()->admin()->create();
        $year = now()->year;
        $date = Carbon::create($year, 3, 15);

        $completed = Transaction::factory()->completed()->create([
            'type' => TransactionType::Buy->value,
            'amount_local' => 1500.00,
            'created_at' => $date,
        ]);

        Transaction::factory()->create([
            'status' => TransactionStatus::Cancelled->value,
            'type' => TransactionType::Buy->value,
            'amount_local' => 2500.00,
            'created_at' => $date,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.monthly-trends', ['year' => $year]));

        $response->assertOk();
        $response->assertViewHas('monthlyData', function ($monthlyData) {
            $march = collect($monthlyData)->firstWhere('month', 3);

            return $march !== null
                && $march['count'] === 1
                && (float) $march['volume'] === 1500.0;
        });
    }
}
