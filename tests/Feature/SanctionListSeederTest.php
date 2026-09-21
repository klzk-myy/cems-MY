<?php

namespace Tests\Feature;

use App\Enums\SanctionListType;
use App\Enums\SanctionSourceFormat;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\SanctionList;
use App\Models\User;
use Database\Seeders\SanctionListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctionListSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseSeeder runs UserSeeder before SanctionListSeeder;
        // sanction_lists.uploaded_by is NOT NULL, so an admin must exist.
        User::factory()->admin()->create();
    }

    public function test_seeder_creates_lists_with_valid_source_format(): void
    {
        $this->seed(SanctionListSeeder::class);

        $lists = SanctionList::all();

        // The seeder is config-driven: one list per config('sanctions.sources') key.
        $this->assertCount(count(config('sanctions.sources')), $lists);

        foreach ($lists as $list) {
            $this->assertContains($list->source_format, SanctionSourceFormat::cases());
        }

        $un = SanctionList::where('slug', 'un_consolidated')->first();
        $this->assertNotNull($un);
        $this->assertSame(SanctionListType::UNSCR, $un->list_type);
        $this->assertTrue((bool) $un->is_active);

        $moha = SanctionList::where('slug', 'moha_malaysia')->first();
        $this->assertNotNull($moha);
        $this->assertSame(SanctionListType::MOHA, $moha->list_type);
    }

    public function test_seeder_creates_demo_entries(): void
    {
        $this->seed(SanctionListSeeder::class);

        $this->assertSame(3, SanctionEntry::count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(SanctionListSeeder::class);
        $this->seed(SanctionListSeeder::class);

        $this->assertSame(count(config('sanctions.sources')), SanctionList::count());
        $this->assertSame(3, SanctionEntry::count());
    }
}
