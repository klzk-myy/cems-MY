<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\HasStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('status_owners', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    #[Test]
    public function scope_active_filters_active_statuses(): void
    {
        $model = new class extends BaseModel
        {
            use HasStatus;

            protected $table = 'status_owners';

            protected $fillable = ['status'];

            protected function activeStatusValues(): array
            {
                return ['active'];
            }

            protected function openStatusValues(): array
            {
                return ['open'];
            }
        };

        $active = $model->newInstance(['status' => 'active']);
        $active->save();

        $draft = $model->newInstance(['status' => 'draft']);
        $draft->save();

        $found = $model->newQuery()->active()->pluck('id');

        $this->assertCount(1, $found);
        $this->assertEquals($active->id, $found->first());
    }

    #[Test]
    public function scope_open_filters_open_statuses(): void
    {
        $model = new class extends BaseModel
        {
            use HasStatus;

            protected $table = 'status_owners';

            protected $fillable = ['status'];

            protected function activeStatusValues(): array
            {
                return [];
            }

            protected function openStatusValues(): array
            {
                return ['open', 'pending'];
            }
        };

        $open = new class(['status' => 'open']) extends BaseModel
        {
            use HasStatus;

            protected $table = 'status_owners';

            protected $fillable = ['status'];

            protected function activeStatusValues(): array
            {
                return [];
            }

            protected function openStatusValues(): array
            {
                return ['open', 'pending'];
            }
        };
        $open->save();

        $pending = new class(['status' => 'pending']) extends BaseModel
        {
            use HasStatus;

            protected $table = 'status_owners';

            protected $fillable = ['status'];

            protected function activeStatusValues(): array
            {
                return [];
            }

            protected function openStatusValues(): array
            {
                return ['open', 'pending'];
            }
        };
        $pending->save();

        $closed = new class(['status' => 'closed']) extends BaseModel
        {
            use HasStatus;

            protected $table = 'status_owners';

            protected $fillable = ['status'];

            protected function activeStatusValues(): array
            {
                return [];
            }

            protected function openStatusValues(): array
            {
                return ['open', 'pending'];
            }
        };
        $closed->save();

        $found = $model->newQuery()->open()->pluck('id')->sort()->values()->toArray();

        $this->assertCount(2, $found);
        $this->assertEqualsCanonicalizing([$open->id, $pending->id], $found);
    }

    #[Test]
    public function is_active_returns_expected_boolean(): void
    {
        $active = new class(['status' => 'active']) extends BaseModel
        {
            use HasStatus;

            protected $table = 'status_owners';

            protected $fillable = ['status'];

            protected function activeStatusValues(): array
            {
                return ['active'];
            }
        };

        $draft = new class(['status' => 'draft']) extends BaseModel
        {
            use HasStatus;

            protected $table = 'status_owners';

            protected $fillable = ['status'];

            protected function activeStatusValues(): array
            {
                return ['active'];
            }
        };

        $this->assertTrue($active->isActive());
        $this->assertFalse($draft->isActive());
    }
}
