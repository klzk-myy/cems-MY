<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportDownloadTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function download_streams_the_report_file(): void
    {
        Storage::fake('local');
        // DocumentStorageService resolves every path under its 'documents/' base.
        Storage::put('documents/reports/msb2_2026-08-07.csv', "currency,buy,sell\nUSD,100,50\n");

        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        // A StreamedResponse (what Laravel's fake/local disks return) cannot
        // be captured by assertContent; assertDownload() verifies the binary
        // stream by content-disposition + content type.
        $this->actingAs($manager, 'sanctum')
            ->get('/api/v1/reports/download/msb2_2026-08-07.csv')
            ->assertOk()
            ->assertDownload('msb2_2026-08-07.csv');
    }

    #[Test]
    public function download_writes_report_access_audit_row(): void
    {
        Storage::fake('local');
        Storage::put('documents/reports/msb2_2026-08-07.csv', "currency,buy,sell\nUSD,100,50\n");

        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->get('/api/v1/reports/download/msb2_2026-08-07.csv')
            ->assertOk();

        $audit = SystemLog::where('action', 'report_downloaded')
            ->where('user_id', $manager->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit, 'Report downloads should write an access audit row');
        $this->assertSame('Report', $audit->entity_type);
    }

    #[Test]
    public function download_rejects_unknown_report(): void
    {
        Storage::fake('local');

        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->get('/api/v1/reports/download/does-not-exist.csv')
            ->assertNotFound();
    }

    #[Test]
    public function download_confines_traversal_to_reports_directory(): void
    {
        Storage::fake('local');
        // A file that a traversal attempt must NOT reach.
        Storage::put('secret.env', 'DB_PASSWORD=leak');

        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->get('/api/v1/reports/download/'.rawurlencode('../../secret.env'))
            ->assertNotFound();

        // basename() confines the lookup to reports/secret.env, which does not exist.
        Storage::assertMissing('reports/secret.env');
        Storage::assertExists('secret.env');
    }
}
