<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CddLevelTest extends TestCase
{
    #[Test]
    public function severity_is_explicit_and_ordered(): void
    {
        $this->assertSame(
            [0, 1, 2, 3],
            array_map(fn (CddLevel $level) => $level->severity(), CddLevel::cases())
        );
    }
}
