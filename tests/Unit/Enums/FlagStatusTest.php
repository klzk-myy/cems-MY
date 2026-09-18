<?php

namespace Tests\Unit\Enums;

use App\Enums\FlagStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlagStatusTest extends TestCase
{
    #[Test]
    public function escalated_value_is_allowed_by_database(): void
    {
        $this->assertSame('escalated', FlagStatus::Escalated->value);
    }
}
