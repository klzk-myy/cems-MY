<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoadTestTest extends TestCase
{
    public function test_load_test_scripts_exist(): void
    {
        $this->assertFileExists(base_path('load-tests/transaction-create.js'));
        $this->assertFileExists(base_path('load-tests/transaction-query.js'));
        $this->assertFileExists(base_path('load-tests/rate-fetch.js'));
    }

    public function test_load_test_scripts_have_valid_structure(): void
    {
        $transactionCreate = file_get_contents(base_path('load-tests/transaction-create.js'));
        $this->assertStringContainsString('export default function', $transactionCreate);
        $this->assertStringContainsString('http_req_duration', $transactionCreate);

        $transactionQuery = file_get_contents(base_path('load-tests/transaction-query.js'));
        $this->assertStringContainsString('export default function', $transactionQuery);

        $rateFetch = file_get_contents(base_path('load-tests/rate-fetch.js'));
        $this->assertStringContainsString('export default function', $rateFetch);
    }
}
