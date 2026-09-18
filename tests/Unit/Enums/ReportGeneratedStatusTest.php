<?php

namespace Tests\Unit\Enums;

use App\Enums\ReportGeneratedStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportGeneratedStatusTest extends TestCase
{
    #[Test]
    public function archived_value_is_allowed_by_database(): void
    {
        $this->assertSame('archived', ReportGeneratedStatus::Archived->value);
    }
}
