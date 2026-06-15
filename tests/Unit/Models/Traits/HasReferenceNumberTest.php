<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\HasReferenceNumber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HasReferenceNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('reference_number_owners', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique()->nullable();
            $table->timestamps();
        });
    }

    public function test_it_generates_sequential_reference_numbers(): void
    {
        $model1 = new class extends BaseModel
        {
            use HasReferenceNumber;

            protected $table = 'reference_number_owners';
        };
        $model1->save();

        $model2 = new class extends BaseModel
        {
            use HasReferenceNumber;

            protected $table = 'reference_number_owners';
        };
        $model2->save();

        $this->assertEquals('REF00000001', $model1->refresh()->reference_number);
        $this->assertEquals('REF00000002', $model2->refresh()->reference_number);
    }

    public function test_it_uses_custom_prefix_and_length(): void
    {
        $model = new class extends BaseModel
        {
            use HasReferenceNumber {
                generateReferenceNumber as public gen;
            }

            protected $table = 'reference_number_owners';

            protected $guarded = [];

            public function __construct()
            {
                $this->referenceNumberPrefix = 'INV';
                $this->referenceNumberLength = 6;
                parent::__construct();
            }

            // Expose for test
            public function getNextNumber(): string
            {
                return $this->gen();
            }
        };
        // Simulate existing last number to test increment
        $model2Class = get_class($model);
        $model2Class::query()->create(['reference_number' => 'INV000002']);

        $new = new $model2Class;
        $ref = $new->getNextNumber();

        $this->assertEquals('INV000003', $ref);
    }

    public function test_existing_reference_number_is_preserved(): void
    {
        $model = new class extends BaseModel
        {
            use HasReferenceNumber;

            protected $table = 'reference_number_owners';
        };
        $model->reference_number = 'CUSTOM-REF';
        $model->save();

        $this->assertEquals('CUSTOM-REF', $model->refresh()->reference_number);
    }
}
