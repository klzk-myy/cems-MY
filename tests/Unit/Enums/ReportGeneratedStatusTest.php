<?php

namespace Tests\Unit\Enums;

use App\Enums\ReportGeneratedStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportGeneratedStatusTest extends TestCase
{
    #[Test]
    public function archived_value_is_allowed_by_database(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->markTestSkipped('Enum constraint only enforced on MySQL.');
        }

        $this->assertSame('Archived', ReportGeneratedStatus::Archived->value);
    }
}
