<?php

namespace Tests\Unit\Support;

use App\Support\DbDate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DbDateTest extends TestCase
{
    #[Test]
    public function it_returns_sqlite_month_expression_for_sqlite(): void
    {
        config(['database.default' => 'sqlite']);

        $this->assertSame("strftime('%m', \"created_at\")", DbDate::monthColumn('created_at'));
    }

    #[Test]
    public function it_returns_mysql_month_expression_for_mysql(): void
    {
        config(['database.default' => 'mysql']);

        $this->assertSame('MONTH(`created_at`)', DbDate::monthColumn('created_at'));
    }

    #[Test]
    public function it_returns_driver_aware_month_bucket_expressions(): void
    {
        config(['database.default' => 'sqlite']);
        $this->assertSame("strftime('%Y-%m', \"created_at\")", DbDate::monthBucket('created_at'));

        config(['database.default' => 'mysql']);
        $this->assertSame("DATE_FORMAT(`created_at`, '%Y-%m')", DbDate::monthBucket('created_at'));
    }
}
