<?php

namespace Tests\Unit\Http\Concerns;

use App\Http\Controllers\Concerns\AuthorizesBranchResource;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class AuthorizesBranchResourceTest extends TestCase
{
    use AuthorizesBranchResource;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_allows_resource_when_branch_id_matches_as_integers(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->branch_id = 5;
        $user->shouldReceive('isAdmin')->andReturn(false);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $resource = Counter::factory()->make(['branch_id' => 5]);

        $this->assertTrue($this->authorizeBranchResource($resource, 'update'));
    }

    public function test_allows_resource_when_branch_id_matches_as_mixed_types(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->branch_id = 5;
        $user->shouldReceive('isAdmin')->andReturn(false);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $resource = Counter::factory()->make(['branch_id' => '5']);

        $this->assertTrue($this->authorizeBranchResource($resource, 'update'));
    }

    public function test_denies_resource_when_branch_id_differs(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->branch_id = 5;
        $user->shouldReceive('isAdmin')->andReturn(false);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $resource = Counter::factory()->make(['branch_id' => 6]);

        $result = $this->authorizeBranchResource($resource, 'update');

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(403, $result->getStatusCode());
    }

    public function test_allows_admin_regardless_of_branch_id(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->branch_id = 5;
        $user->shouldReceive('isAdmin')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $resource = Counter::factory()->make(['branch_id' => 99]);

        $this->assertTrue($this->authorizeBranchResource($resource, 'update'));
    }

    public function test_denies_unauthenticated_user(): void
    {
        Auth::shouldReceive('user')->once()->andReturn(null);

        $resource = Counter::factory()->make(['branch_id' => 5]);

        $result = $this->authorizeBranchResource($resource, 'update');

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(401, $result->getStatusCode());
    }

    public function test_allows_branch_resource_when_key_matches_user_branch_id(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->branch_id = 5;
        $user->shouldReceive('isAdmin')->andReturn(false);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $branch = Branch::factory()->make(['id' => 5]);

        $this->assertTrue($this->authorizeBranchResource($branch, 'access'));
    }
}
