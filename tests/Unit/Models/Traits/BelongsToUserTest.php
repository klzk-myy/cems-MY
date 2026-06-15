<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BelongsToUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('user_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function test_it_adds_user_id_to_fillable(): void
    {
        $model = new class extends BaseModel
        {
            use BelongsToUser;

            protected $table = 'user_owners';
        };

        $this->assertContains('user_id', $model->getFillable());
    }

    public function test_it_defines_user_relationship(): void
    {
        $user = User::factory()->create();

        $model = new class(['user_id' => $user->id]) extends BaseModel
        {
            use BelongsToUser;

            protected $table = 'user_owners';
        };
        $model->save();

        $this->assertInstanceOf(BelongsTo::class, $model->user());
        $this->assertTrue($model->user()->is($user));
    }

    public function test_scope_for_user_filters_by_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $modelA = new class(['user_id' => $userA->id]) extends BaseModel
        {
            use BelongsToUser;

            protected $table = 'user_owners';
        };
        $modelA->save();

        $modelB = new class(['user_id' => $userB->id]) extends BaseModel
        {
            use BelongsToUser;

            protected $table = 'user_owners';
        };
        $modelB->save();

        $found = $modelA->newQuery()->forUser($userA->id)->pluck('id');

        $this->assertCount(1, $found);
        $this->assertEquals($modelA->id, $found->first());
    }
}
