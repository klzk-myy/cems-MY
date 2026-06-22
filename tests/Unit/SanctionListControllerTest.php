<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Http\Controllers\Compliance\SanctionListController;
use App\Http\Requests\UpdateSanctionEntryRequest;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionListControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_entry_saves_listing_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $list = SanctionList::factory()->create();
        $entry = SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'listing_date' => null,
            'details' => null,
            'aliases' => null,
        ]);

        $data = [
            'entity_name' => $entry->entity_name,
            'entity_type' => $entry->entity_type?->value ?? 'Individual',
            'date_listed' => '2024-03-15',
            'list_source' => $entry->list_source,
        ];

        $formRequest = $this->getMockBuilder(UpdateSanctionEntryRequest::class)
            ->disableOriginalConstructor()
            ->getMock();

        $formRequest->expects($this->any())
            ->method('validated')
            ->willReturn($data);

        $formRequest->expects($this->any())
            ->method('has')
            ->willReturnCallback(fn ($key) => array_key_exists($key, $data));

        $formRequest->expects($this->any())
            ->method('input')
            ->willReturnCallback(fn ($key, $default = null) => $data[$key] ?? $default);

        $formRequest->expects($this->any())
            ->method('merge')
            ->willReturnCallback(function ($arr) use (&$data) {
                $data = array_merge($data, $arr);
            });

        $controller = $this->app->make(SanctionListController::class);
        $response = $controller->updateEntry($formRequest, $entry);

        $this->assertEquals('2024-03-15', $entry->fresh()->listing_date?->format('Y-m-d'));
        $this->assertEquals(302, $response->getStatusCode());
    }
}
