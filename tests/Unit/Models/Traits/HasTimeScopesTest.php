<?php

namespace Tests\Unit\Models\Traits;

use App\Models\BaseModel;
use App\Models\Traits\HasTimeScopes;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HasTimeScopesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('time_scopes_owners', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function test_scope_latest_orders_by_created_at_desc(): void
    {
        $older = new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        };
        $older->created_at = Carbon::now()->subDay();
        $older->save();

        $newer = new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        };
        $newer->save(); // now

        $result = (new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        })->newQuery()->latest()->pluck('id')->toArray();

        $this->assertEquals([$newer->id, $older->id], $result);
    }

    public function test_scope_today_filters_today_records(): void
    {
        $today = new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        };
        $today->save();

        $yesterday = new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        };
        $yesterday->created_at = Carbon::yesterday();
        $yesterday->save();

        $result = (new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        })->newQuery()->today()->pluck('id')->toArray();

        $this->assertEquals([$today->id], $result);
    }

    public function test_scope_between_dates_filters_correctly(): void
    {
        $from = Carbon::now()->subDays(2);
        $to = Carbon::now()->subDays(1);

        $inside = new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        };
        $inside->created_at = Carbon::now()->subDays(1)->addHour();
        $inside->save();

        $outside1 = new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        };
        $outside1->created_at = Carbon::now()->subDays(3);
        $outside1->save();

        $outside2 = new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        };
        $outside2->created_at = Carbon::now()->addHour();
        $outside2->save();

        $result = (new class extends BaseModel
        {
            use HasTimeScopes;

            protected $table = 'time_scopes_owners';
        })->newQuery()->betweenDates($from->toDateString(), $to->toDateString())->pluck('id')->toArray();

        $this->assertEquals([$inside->id], $result);
    }
}
