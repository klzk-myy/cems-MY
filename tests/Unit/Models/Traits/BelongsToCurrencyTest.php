<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Currency;
use App\Models\Traits\BelongsToCurrency;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BelongsToCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('currency_owners', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_adds_currency_code_to_fillable(): void
    {
        $model = new class extends BaseModel
        {
            use BelongsToCurrency;

            protected $table = 'currency_owners';
        };

        $this->assertContains('currency_code', $model->getFillable());
    }

    public function test_it_defines_currency_relationship(): void
    {
        $currency = Currency::create([
            'code' => 'JPY',
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $model = new class(['currency_code' => 'JPY']) extends BaseModel
        {
            use BelongsToCurrency;

            protected $table = 'currency_owners';
        };
        $model->save();

        $this->assertInstanceOf(BelongsTo::class, $model->currency());
        $this->assertTrue($model->currency()->is($currency));
    }

    public function test_scope_for_currency_filters_by_currency(): void
    {
        Currency::create(['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimal_places' => 2, 'is_active' => true]);
        Currency::create(['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2, 'is_active' => true]);

        $modelA = new class(['currency_code' => 'JPY']) extends BaseModel
        {
            use BelongsToCurrency;

            protected $table = 'currency_owners';
        };
        $modelA->save();

        $modelB = new class(['currency_code' => 'CNY']) extends BaseModel
        {
            use BelongsToCurrency;

            protected $table = 'currency_owners';
        };
        $modelB->save();

        $found = $modelA->newQuery()->forCurrency('JPY')->pluck('id');

        $this->assertCount(1, $found);
        $this->assertEquals($modelA->id, $found->first());
    }
}
