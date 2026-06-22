<?php

namespace Tests\Unit\Enums;

use App\Enums\FlagStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlagStatusTest extends TestCase
{
    #[Test]
    public function escalated_value_is_allowed_by_database(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->markTestSkipped('Enum constraint only enforced on MySQL.');
        }

        $this->assertSame('Escalated', FlagStatus::Escalated->value);
    }
}
