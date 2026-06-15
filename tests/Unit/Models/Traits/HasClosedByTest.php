<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\HasClosedBy;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HasClosedByTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('closed_by_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_adds_closed_fields_to_fillable_and_casts(): void
    {
        $model = new class extends BaseModel
        {
            use HasClosedBy;

            protected $table = 'closed_by_owners';
        };

        $this->assertContains('closed_by', $model->getFillable());
        $this->assertContains('closed_at', $model->getFillable());
        $this->assertArrayHasKey('closed_at', $model->getCasts());
    }

    public function test_close_sets_user_and_timestamp(): void
    {
        $user = User::factory()->create();

        $model = new class extends BaseModel
        {
            use HasClosedBy;

            protected $table = 'closed_by_owners';
        };
        $model->save();

        $model->close($user);

        $this->assertTrue($model->closedBy()->is($user));
        $this->assertNotNull($model->closed_at);
    }
}
