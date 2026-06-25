<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BelongsToBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('branch_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->timestamps();
        });
    }

    #[Test]
    public function it_adds_branch_id_to_fillable(): void
    {
        $model = new class extends BaseModel
        {
            use BelongsToBranch;

            protected $table = 'branch_owners';
        };

        $this->assertContains('branch_id', $model->getFillable());
    }

    #[Test]
    public function it_defines_branch_relationship(): void
    {
        $branch = Branch::create([
            'code' => 'HQ',
            'name' => 'Headquarters',
            'is_active' => true,
        ]);

        $model = new class(['branch_id' => $branch->id]) extends BaseModel
        {
            use BelongsToBranch;

            protected $table = 'branch_owners';
        };
        $model->save();

        $this->assertInstanceOf(BelongsTo::class, $model->branch());
        $this->assertTrue($model->branch()->is($branch));
    }

    #[Test]
    public function scope_for_branch_filters_by_branch(): void
    {
        $branchA = Branch::create(['code' => 'A', 'name' => 'A', 'is_active' => true]);
        $branchB = Branch::create(['code' => 'B', 'name' => 'B', 'is_active' => true]);

        $modelA = new class(['branch_id' => $branchA->id]) extends BaseModel
        {
            use BelongsToBranch;

            protected $table = 'branch_owners';
        };
        $modelA->save();

        $modelB = new class(['branch_id' => $branchB->id]) extends BaseModel
        {
            use BelongsToBranch;

            protected $table = 'branch_owners';
        };
        $modelB->save();

        $found = $modelA->newQuery()->forBranch($branchA->id)->pluck('id');

        $this->assertCount(1, $found);
        $this->assertEquals($modelA->id, $found->first());
    }
}
