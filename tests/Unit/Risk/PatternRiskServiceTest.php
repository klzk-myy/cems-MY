<?php

namespace Tests\Unit\Risk;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\Compliance\RoundTripDetector;
use App\Services\System\MathService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PatternRiskServiceTest extends TestCase
{
    private function makeTransaction(array $attrs): Transaction
    {
        // Set attributes individually (not via the constructor) because `created_at`
        // and `updated_at` are not mass-assignable, so `new Transaction([...])` would
        // silently drop `created_at` and leave it null.
        $model = new Transaction;
        $model->id = $attrs['id'];
        $model->currency_code = $attrs['currency_code'];
        $model->type = $attrs['type'];
        $model->quantity = $attrs['quantity'];
        $model->amount_myr = $attrs['amount_myr'];
        $model->created_at = $attrs['created_at'];

        return $model;
    }

    #[Test]
    public function round_trip_detector_uses_math_service_api(): void
    {
        // The shared detector must use the consistent MathService API, not a
        // legacy `$this->math->compare` property.
        $file = base_path('app/Services/Compliance/RoundTripDetector.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('$this->mathService->compare', $content);
        $this->assertStringNotContainsString('$this->math->compare', $content);
    }

    #[Test]
    public function round_trip_detector_flags_sell_then_buy_within_window(): void
    {
        $detector = new RoundTripDetector(new MathService);

        $now = Carbon::parse('2026-01-20 12:00:00');
        $transactions = new Collection([
            $this->makeTransaction([
                'id' => 1,
                'currency_code' => 'USD',
                'type' => TransactionType::Sell,
                'quantity' => '6000.00',
                'amount_myr' => '26000.00',
                'created_at' => $now->copy()->subHours(2),
            ]),
            $this->makeTransaction([
                'id' => 2,
                'currency_code' => 'USD',
                'type' => TransactionType::Buy,
                'quantity' => '6000.00',
                'amount_myr' => '26400.00',
                'created_at' => $now,
            ]),
        ]);

        $patterns = $detector->detect($transactions, 72, '5000');

        $this->assertCount(1, $patterns);
        $this->assertSame('USD', $patterns[0]['currency']);
        $this->assertSame(1, $patterns[0]['sell_transaction_id']);
        $this->assertSame(2, $patterns[0]['buy_transaction_id']);
        $this->assertSame('6000.0000', $patterns[0]['round_trip_foreign_amount']);
    }

    #[Test]
    public function round_trip_detector_ignores_buy_before_sell(): void
    {
        $detector = new RoundTripDetector(new MathService);

        $now = Carbon::parse('2026-01-20 12:00:00');
        $transactions = new Collection([
            $this->makeTransaction([
                'id' => 1,
                'currency_code' => 'USD',
                'type' => TransactionType::Buy,
                'quantity' => '6000.00',
                'amount_myr' => '26400.00',
                'created_at' => $now->copy()->subHours(2),
            ]),
            $this->makeTransaction([
                'id' => 2,
                'currency_code' => 'USD',
                'type' => TransactionType::Sell,
                'quantity' => '6000.00',
                'amount_myr' => '26000.00',
                'created_at' => $now,
            ]),
        ]);

        // A buy before a sell is not a round-trip (buy must come after sell).
        $patterns = $detector->detect($transactions, 72, '5000');
        $this->assertCount(0, $patterns);
    }

    #[Test]
    public function round_trip_detector_respects_time_window(): void
    {
        $detector = new RoundTripDetector(new MathService);

        $now = Carbon::parse('2026-01-20 12:00:00');
        $transactions = new Collection([
            $this->makeTransaction([
                'id' => 1,
                'currency_code' => 'USD',
                'type' => TransactionType::Sell,
                'quantity' => '6000.00',
                'amount_myr' => '26000.00',
                'created_at' => $now->copy()->subHours(100),
            ]),
            $this->makeTransaction([
                'id' => 2,
                'currency_code' => 'USD',
                'type' => TransactionType::Buy,
                'quantity' => '6000.00',
                'amount_myr' => '26400.00',
                'created_at' => $now,
            ]),
        ]);

        // Sell and buy 100h apart exceed the 72h window.
        $patterns = $detector->detect($transactions, 72, '5000');
        $this->assertCount(0, $patterns);
    }

    #[Test]
    public function round_trip_detector_respects_threshold(): void
    {
        $detector = new RoundTripDetector(new MathService);

        $now = Carbon::parse('2026-01-20 12:00:00');
        $transactions = new Collection([
            $this->makeTransaction([
                'id' => 1,
                'currency_code' => 'USD',
                'type' => TransactionType::Sell,
                'quantity' => '3000.00',
                'amount_myr' => '13000.00',
                'created_at' => $now->copy()->subHours(1),
            ]),
            $this->makeTransaction([
                'id' => 2,
                'currency_code' => 'USD',
                'type' => TransactionType::Buy,
                'quantity' => '3000.00',
                'amount_myr' => '13200.00',
                'created_at' => $now,
            ]),
        ]);

        // Amounts below the 5000 threshold are not flagged.
        $patterns = $detector->detect($transactions, 72, '5000');
        $this->assertCount(0, $patterns);
    }
}
