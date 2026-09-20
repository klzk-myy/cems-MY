<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\SanctionList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slow')]
class ListPaginationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    #[Test]
    public function collection_paginate_macro_returns_length_aware_paginator(): void
    {
        $items = collect(range(1, 30));
        $page = $items->paginate(25);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertSame(30, $page->total());
        $this->assertCount(25, $page->items());
        $this->assertSame(2, $page->lastPage());
    }

    #[Test]
    public function currencies_index_paginates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Seed enough rows to exceed one page (25).
        for ($i = 0; $i < 30; $i++) {
            Currency::firstOrCreate(
                ['code' => 'X'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                ['name' => 'Test '.$i, 'symbol' => 'T', 'decimal_places' => 2, 'is_active' => true]
            );
        }

        $response = $this->actingAs($admin)->get('/system/currencies');

        $response->assertOk();
        $response->assertSee('aria-label="Pagination', escape: false);
    }

    #[Test]
    public function sanctions_index_paginates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        for ($i = 0; $i < 26; $i++) {
            SanctionList::factory()->create(['name' => 'Test List '.$i]);
        }

        $page1 = $this->actingAs($admin)->get('/compliance/sanctions');
        $page1->assertOk();
        // More than one page of rows must render a Next link carrying page=2.
        $page1->assertSee('page=2', escape: false);

        $page2 = $this->actingAs($admin)->get('/compliance/sanctions?page=2');
        $page2->assertOk();
    }

    #[Test]
    public function branch_pools_index_handles_both_branch_scopes(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branch->id]);

        BranchPool::create([
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'total_quantity' => '1000.00',
            'available_quantity' => '1000.00',
            'allocated_quantity' => '0.00',
        ]);

        $this->actingAs($manager)->get('/branch-pools')->assertOk();
    }
}
