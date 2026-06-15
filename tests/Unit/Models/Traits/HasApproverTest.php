<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\HasApprover;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HasApproverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('approver_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_adds_approval_fields_to_fillable_and_casts(): void
    {
        $model = new class extends BaseModel
        {
            use HasApprover;

            protected $table = 'approver_owners';
        };

        $this->assertContains('approved_by', $model->getFillable());
        $this->assertContains('approved_at', $model->getFillable());
        $this->assertArrayHasKey('approved_at', $model->getCasts());
    }

    public function test_approve_sets_user_and_timestamp(): void
    {
        $user = User::factory()->create();

        $model = new class extends BaseModel
        {
            use HasApprover;

            protected $table = 'approver_owners';
        };
        $model->save();

        $model->approve($user);

        $this->assertTrue($model->approver()->is($user));
        $this->assertNotNull($model->approved_at);
    }
}
