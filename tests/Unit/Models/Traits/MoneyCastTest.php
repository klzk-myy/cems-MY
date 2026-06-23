<?php

namespace Tests\Unit\Models\Traits;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoneyCastTest extends TestCase
{
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

    #[Test]
    public function get_returns_scaled_string(): void
    {
        $m = $this->model();
        $m->amount = '123.45678';
        $this->assertSame('123.4568', $m->amount);
    }

    #[Test]
    public function set_accepts_numeric_strings_and_numbers(): void
    {
        $m = $this->model();
        $m->amount = '99.999';
        $this->assertSame('99.9990', $m->amount);

        $m->amount = 42.5;
        $this->assertSame('42.5000', $m->amount);
    }

    #[Test]
    public function null_is_preserved(): void
    {
        $m = $this->model();
        $m->amount = null;
        $this->assertNull($m->amount);
    }

    #[Test]
    public function non_numeric_throws(): void
    {
        $m = $this->model();
        $this->expectException(\InvalidArgumentException::class);
        $m->amount = 'abc';
    }
}
