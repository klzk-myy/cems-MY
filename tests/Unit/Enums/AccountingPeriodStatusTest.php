<?php

namespace Tests\Unit\Enums;

use App\Enums\AccountingPeriodStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingPeriodStatusTest extends TestCase
{
    #[Test]
    public function values_match_database_enum(): void
    {
        $this->assertSame('Open', AccountingPeriodStatus::Open->value);
        $this->assertSame('Closed', AccountingPeriodStatus::Closed->value);
        $this->assertSame('Locked', AccountingPeriodStatus::Locked->value);
    }

    #[Test]
    public function locked_status_is_supported(): void
    {
        $this->assertTrue(AccountingPeriodStatus::Locked->isLocked());
        $this->assertSame('dark', AccountingPeriodStatus::Locked->color());
    }
}
