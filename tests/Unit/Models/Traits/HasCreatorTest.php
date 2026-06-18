<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\HasCreator;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasCreatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('creator_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    #[Test]
    public function it_adds_created_by_to_fillable(): void
    {
        $model = new class extends BaseModel
        {
            use HasCreator;

            protected $table = 'creator_owners';
        };

        $this->assertContains('created_by', $model->getFillable());
    }

    #[Test]
    public function it_defines_creator_relationship(): void
    {
        $user = User::factory()->create();

        $model = new class(['created_by' => $user->id]) extends BaseModel
        {
            use HasCreator;

            protected $table = 'creator_owners';
        };
        $model->save();

        $this->assertTrue($model->creator()->is($user));
    }
}
