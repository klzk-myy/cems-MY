<?php

namespace Tests\Unit\View\Components;

use App\View\Components\DataTable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataTableTest extends TestCase
{
    #[Test]
    public function datatable_has_data(): void
    {
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('isNotEmpty')->willReturn(true);

        $component = new DataTable(data: $mockPaginator, columns: [
            ['key' => 'name', 'label' => 'Name'],
        ]);

        $this->assertTrue($component->hasData());
    }

    #[Test]
    public function datatable_empty_state(): void
    {
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('isNotEmpty')->willReturn(false);

        $component = new DataTable(
            data: $mockPaginator,
            columns: [['key' => 'name', 'label' => 'Name']],
            emptyMessage: 'No users found'
        );

        $this->assertFalse($component->hasData());
        $this->assertEquals(2, $component->getColumnCount());
    }

    #[Test]
    public function datatable_column_count(): void
    {
        $component = new DataTable(columns: [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'role', 'label' => 'Role'],
        ]);

        $this->assertEquals(4, $component->getColumnCount());
    }

    #[Test]
    public function datatable_renders_view(): void
    {
        $component = new DataTable(columns: []);

        $view = $component->render();

        $this->assertNotNull($view);
    }

    #[Test]
    public function datatable_can_hide_actions_column(): void
    {
        $component = new DataTable(
            data: null,
            columns: [['key' => 'name', 'label' => 'Name']],
            hasActions: false
        );

        $this->assertEquals(1, $component->getColumnCount());
    }
}
