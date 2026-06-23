<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\BelongsToCustomer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BelongsToCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customer_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->timestamps();
        });
    }

    #[Test]
    public function it_adds_customer_id_to_fillable(): void
    {
        $model = new class extends BaseModel
        {
            use BelongsToCustomer;

            protected $table = 'customer_owners';
        };

        $this->assertContains('customer_id', $model->getFillable());
    }

    #[Test]
    public function it_defines_customer_relationship(): void
    {
        $customer = $this->createTestCustomer();

        $model = new class(['customer_id' => $customer->id]) extends BaseModel
        {
            use BelongsToCustomer;

            protected $table = 'customer_owners';
        };
        $model->save();

        $this->assertInstanceOf(BelongsTo::class, $model->customer());
        $this->assertTrue($model->customer()->is($customer));
    }

    #[Test]
    public function scope_for_customer_filters_by_customer(): void
    {
        $customerA = $this->createTestCustomer();
        $customerB = $this->createTestCustomer();

        $modelA = new class(['customer_id' => $customerA->id]) extends BaseModel
        {
            use BelongsToCustomer;

            protected $table = 'customer_owners';
        };
        $modelA->save();

        $modelB = new class(['customer_id' => $customerB->id]) extends BaseModel
        {
            use BelongsToCustomer;

            protected $table = 'customer_owners';
        };
        $modelB->save();

        $found = $modelA->newQuery()->forCustomer($customerA->id)->pluck('id');

        $this->assertCount(1, $found);
        $this->assertEquals($modelA->id, $found->first());
    }
}
