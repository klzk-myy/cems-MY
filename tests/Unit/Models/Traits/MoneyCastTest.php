<?php

namespace Tests\Unit\Models\Traits;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MoneyCastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('money_cast_owners', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 15, 4)->nullable();
            $table->timestamps();
        });
    }

    private function model(): Model
    {
        return new class extends Model
        {
            protected $table = 'money_cast_owners';

            protected $casts = [
                'amount' => MoneyCast::class,
            ];
        };
    }

    public function test_get_returns_scaled_string(): void
    {
        $m = $this->model();
        $m->amount = '123.45678';
        $this->assertSame('123.4568', $m->amount);
    }

    public function test_set_accepts_numeric_strings_and_numbers(): void
    {
        $m = $this->model();
        $m->amount = '99.999';
        $this->assertSame('99.9990', $m->amount);

        $m->amount = 42.5;
        $this->assertSame('42.5000', $m->amount);
    }

    public function test_null_is_preserved(): void
    {
        $m = $this->model();
        $m->amount = null;
        $this->assertNull($m->amount);
    }

    public function test_non_numeric_throws(): void
    {
        $m = $this->model();
        $this->expectException(\InvalidArgumentException::class);
        $m->amount = 'abc';
    }
}
