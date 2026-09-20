<?php

namespace Tests\Feature\Audit;

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\ReportController;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportDownloadSanitizationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A traversal filename must resolve inside the reports directory: when a
     * same-named file exists both under reports/ and outside it, the download
     * serves the reports copy. The router rejects raw ../ segments before the
     * controller, so the controller is invoked directly to exercise basename()
     * confinement itself. (404-on-missing coverage lives in
     * tests/Feature/Api/ReportDownloadTest.)
     */
    public function test_traversal_filename_resolves_inside_reports_directory(): void
    {
        Storage::fake('local');
        // DocumentStorageService resolves every path under its 'documents/' base.
        Storage::put('documents/reports/secret.env', 'reports-copy');
        Storage::put('documents/secret.env', 'outside-copy');

        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);
        $this->actingAs($manager);

        $response = app(ReportController::class)->download('../../secret.env');

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertSame('reports-copy', $content);
    }
}
