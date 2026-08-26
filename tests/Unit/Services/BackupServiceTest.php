<?php

namespace Tests\Unit\Services;

use App\Services\System\BackupService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    #[Test]
    public function service_can_be_instantiated(): void
    {
        $service = app(BackupService::class);

        $this->assertInstanceOf(BackupService::class, $service);
    }
}
