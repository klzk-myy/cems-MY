<?php

namespace Tests\Unit\Services\Reporting;

use App\Services\Reporting\CsvReportWriter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvReportWriterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_write_creates_csv_with_headers_and_rows(): void
    {
        $writer = new CsvReportWriter;

        $headers = ['Name', 'Amount'];
        $rows = [
            ['Alice', '100'],
            ['Bob', '200'],
        ];

        $filepath = $writer->write('test_report.csv', $headers, $rows);

        Storage::disk('local')->assertExists($filepath);
        $content = Storage::disk('local')->get($filepath);
        $this->assertStringContainsString('Name,Amount', $content);
        $this->assertStringContainsString('Alice,100', $content);
        $this->assertStringContainsString('Bob,200', $content);
    }

    public function test_write_with_title_rows_includes_title_and_blank_separator(): void
    {
        $writer = new CsvReportWriter;

        $titleRows = [
            ['Report Title'],
            ['Generated', '2024-01-01'],
        ];
        $headers = ['Column'];
        $rows = [['Value']];

        $filepath = $writer->writeWithTitleRows('titled_report.csv', $titleRows, $headers, $rows);

        Storage::disk('local')->assertExists($filepath);
        $lines = explode("\n", trim(Storage::disk('local')->get($filepath)));
        $this->assertSame('"Report Title"', $lines[0]);
        $this->assertSame('Generated,2024-01-01', $lines[1]);
        $this->assertSame('', $lines[2]); // blank separator row
        $this->assertSame('Column', $lines[3]);
        $this->assertSame('Value', $lines[4]);
    }

    public function test_write_escapes_values_with_special_characters(): void
    {
        $writer = new CsvReportWriter;

        $headers = ['Note'];
        $rows = [['Contains, comma and "quotes"']];

        $filepath = $writer->write('escaped_report.csv', $headers, $rows);

        $content = Storage::disk('local')->get($filepath);
        $this->assertStringContainsString('"Contains, comma and ""quotes"""', $content);
    }
}
