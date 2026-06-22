<?php

namespace Tests\Unit\Enums;

use App\Enums\ReportRunStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportRunStatusTest extends TestCase
{
    #[Test]
    public function values_match_report_runs_column(): void
    {
        $this->assertSame('scheduled', ReportRunStatus::Scheduled->value);
        $this->assertSame('running', ReportRunStatus::Running->value);
        $this->assertSame('completed', ReportRunStatus::Completed->value);
        $this->assertSame('failed', ReportRunStatus::Failed->value);
    }

    #[Test]
    public function label_and_color_cover_all_cases(): void
    {
        foreach (ReportRunStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
            $this->assertNotEmpty($status->color());
        }
    }
}
