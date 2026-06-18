<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\HasReferenceNumber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasReferenceNumberRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('race_test_models', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique()->nullable();
            $table->timestamps();
        });
    }

    #[Test]
    public function concurrent_generators_produce_unique_reference_numbers(): void
    {
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $model = new class extends BaseModel
            {
                use HasReferenceNumber;

                protected $table = 'race_test_models';

                protected $guarded = [];
            };
            $model->save();
            $results[] = $model->refresh()->reference_number;
        }

        $unique = array_unique($results);
        $this->assertCount(10, $unique, 'Reference numbers must be unique even under concurrent generation');
    }
}
