<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoadTestTest extends TestCase
{
    public function test_k6_is_available()
    {
        $result = shell_exec('which k6 2>/dev/null');
        $this->markTestSkippedWhenK6NotAvailable($result);

        $this->assertNotEmpty($result, 'k6 should be installed for load testing');
    }

    public function test_load_test_scripts_exist()
    {
        $this->assertFileExists(base_path('load-tests/transaction-create.js'));
        $this->assertFileExists(base_path('load-tests/transaction-query.js'));
        $this->assertFileExists(base_path('load-tests/rate-fetch.js'));
    }

    public function test_load_test_scripts_have_valid_structure()
    {
        $transactionCreate = file_get_contents(base_path('load-tests/transaction-create.js'));
        $this->assertStringContainsString('export default function', $transactionCreate);
        $this->assertStringContainsString('http_req_duration', $transactionCreate);

        $transactionQuery = file_get_contents(base_path('load-tests/transaction-query.js'));
        $this->assertStringContainsString('export default function', $transactionQuery);

        $rateFetch = file_get_contents(base_path('load-tests/rate-fetch.js'));
        $this->assertStringContainsString('export default function', $rateFetch);
    }

    protected function markTestSkippedWhenK6NotAvailable(?string $result): void
    {
        if (empty($result)) {
            $this->markTestSkipped('k6 not installed - install from https://k6.io/');
        }
    }
}
