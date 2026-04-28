<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_user_permissions_uses_cache()
    {
        $user = User::factory()->create();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(['transactions.create', 'transactions.view']);

        $service = app(UserService::class);
        $permissions = $service->getUserPermissions($user->id);

        $this->assertIsArray($permissions);
    }
}
