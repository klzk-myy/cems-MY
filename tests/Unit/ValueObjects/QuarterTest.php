<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Quarter;
use PHPUnit\Framework\TestCase;

class QuarterTest extends TestCase
{
    public function test_from_string_parses_valid_quarters(): void
    {
        $quarter = Quarter::fromString('2024-Q2');

        $this->assertSame(2024, $quarter->year);
        $this->assertSame(2, $quarter->quarter);
        $this->assertSame('2024-Q2', $quarter->toString());
    }

    public function test_from_string_throws_for_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quarter::fromString('not-a-quarter');
    }

    public function test_from_string_throws_for_out_of_range_quarter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quarter::fromString('2024-Q5');
    }

    /**
     * @dataProvider quarterDateProvider
     */
    public function test_start_and_end_dates(int $year, int $quarterNumber, string $expectedStart, string $expectedEnd): void
    {
        $quarter = new Quarter($year, $quarterNumber);

        $this->assertSame($expectedStart, $quarter->startDate()->toDateTimeString());
        $this->assertSame($expectedEnd, $quarter->endDate()->toDateTimeString());
    }

    public static function quarterDateProvider(): array
    {
        return [
            'Q1' => [2024, 1, '2024-01-01 00:00:00', '2024-03-31 23:59:59'],
            'Q2' => [2024, 2, '2024-04-01 00:00:00', '2024-06-30 23:59:59'],
            'Q3' => [2024, 3, '2024-07-01 00:00:00', '2024-09-30 23:59:59'],
            'Q4 end of year' => [2024, 4, '2024-10-01 00:00:00', '2024-12-31 23:59:59'],
            'leap year Q1' => [2024, 1, '2024-01-01 00:00:00', '2024-03-31 23:59:59'],
        ];
    }

    public function test_to_string_round_trips(): void
    {
        $original = '2023-Q4';
        $quarter = Quarter::fromString($original);

        $this->assertSame($original, $quarter->toString());
        $this->assertSame($original, Quarter::fromString($quarter->toString())->toString());
    }
}
